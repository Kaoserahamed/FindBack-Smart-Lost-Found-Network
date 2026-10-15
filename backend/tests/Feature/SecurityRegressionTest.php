<?php

namespace Tests\Feature;

use App\Livewire\ReportCreate;
use App\Models\Category;
use App\Models\Claim;
use App\Models\Community;
use App\Models\CommunityMembership;
use App\Models\ItemEvent;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression tests for the vulnerabilities found in the security audit.
 * Each test reproduces the exact attack that previously succeeded.
 */
class SecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function foundReport(User $owner, Community $c, Category $cat): Report
    {
        return Report::factory()->found()->create([
            'user_id' => $owner->id,
            'community_id' => $c->id,
            'category_id' => $cat->id,
            'status' => 'open',
        ]);
    }

    private function join(User $u, Community $c, string $role = 'member', string $status = 'active'): void
    {
        CommunityMembership::create([
            'community_id' => $c->id, 'user_id' => $u->id, 'role' => $role, 'status' => $status,
        ]);
    }

    /** C1: a non-member of the community must NOT be able to claim. */
    public function test_non_member_of_community_cannot_claim(): void
    {
        $communityA = Community::factory()->university()->create();
        $communityB = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $report = $this->foundReport(User::factory()->create(), $communityA, $category);

        $outsider = User::factory()->create();
        $this->join($outsider, $communityB); // member of B only

        $this->actingAs($outsider)->post(route('claims.store', $report), [
            'report_id' => $report->id,
            'answers' => ['q1' => 'I am not a member of this community.'],
        ])->assertForbidden();

        $this->assertSame(0, Claim::count());
    }

    /** C1: the reporter must NOT be able to claim their own found report. */
    public function test_reporter_cannot_claim_own_report(): void
    {
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $owner = User::factory()->create();
        $this->join($owner, $community);
        $report = $this->foundReport($owner, $community, $category);

        $this->actingAs($owner)->post(route('claims.store', $report), [
            'report_id' => $report->id,
            'answers' => ['q1' => 'Self-claim.'],
        ])->assertForbidden();

        $this->assertSame(0, Claim::count());
    }

    /** C1: a resolved report must no longer accept claims. */
    public function test_cannot_claim_a_resolved_report(): void
    {
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $report = $this->foundReport(User::factory()->create(), $community, $category);
        $report->update(['status' => 'resolved']);

        $claimant = User::factory()->create();
        $this->join($claimant, $community);

        $this->actingAs($claimant)->post(route('claims.store', $report), [
            'report_id' => $report->id,
            'answers' => ['q1' => 'answer'],
        ])->assertSessionHasErrors('report_id');

        $this->assertSame(0, Claim::count());
    }

    /** C4: answer values are bounded scalars. */
    public function test_oversized_and_nested_answers_are_rejected(): void
    {
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $report = $this->foundReport(User::factory()->create(), $community, $category);
        $claimant = User::factory()->create();
        $this->join($claimant, $community);

        // 200 KB single answer.
        $this->actingAs($claimant)->post(route('claims.store', $report), [
            'report_id' => $report->id,
            'answers' => ['q1' => str_repeat('A', 200_000)],
        ])->assertSessionHasErrors('answers.q1');

        // Arbitrarily nested JSON where a scalar is expected.
        $this->actingAs($claimant)->post(route('claims.store', $report), [
            'report_id' => $report->id,
            'answers' => ['q1' => ['nested' => ['deep' => 'x']]],
        ])->assertSessionHasErrors('answers.q1');

        $this->assertSame(0, Claim::count());
    }

    /** C2: a rejected claim cannot be re-approved and handed over twice. */
    public function test_rejected_claim_cannot_be_reopened(): void
    {
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $owner = User::factory()->create();
        $report = $this->foundReport($owner, $community, $category);
        $claim = Claim::factory()->create([
            'report_id' => $report->id,
            'claimant_id' => User::factory()->create()->id,
            'status' => 'rejected',
        ]);

        $this->actingAs($owner)->patch(route('claims.review', $claim), [
            'decision' => 'approved', 'decision_reason' => 'Flip-flopped.',
        ])->assertStatus(422);

        $this->assertSame('rejected', $claim->fresh()->status);
    }

    /** C2: a completed claim cannot be re-approved and handed over again. */
    public function test_completed_claim_cannot_be_reapproved(): void
    {
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $owner = User::factory()->create();
        $report = $this->foundReport($owner, $community, $category);
        $claim = Claim::factory()->create([
            'report_id' => $report->id,
            'claimant_id' => User::factory()->create()->id,
            'status' => 'approved',
        ]);

        $this->actingAs($owner)->post(route('claims.complete-handover', $claim))->assertRedirect();
        $this->assertSame('completed', $claim->fresh()->status);

        // Re-approve, then attempt a second handover.
        $this->actingAs($owner)->patch(route('claims.review', $claim), [
            'decision' => 'approved', 'decision_reason' => 'Re-approved.',
        ])->assertStatus(422);

        $this->actingAs($owner)->post(route('claims.complete-handover', $claim))->assertStatus(422);

        $this->assertSame(
            1,
            ItemEvent::where('report_id', $report->id)->where('action', 'custody_transferred')->count()
        );
    }

    /**
     * C2: one item cannot be handed to two different claimants.
     *
     * claimA is created already-approved (handed over below). claimB starts as
     * submitted and is only approved afterwards, because the new
     * claims_one_winner_per_report partial unique index makes two simultaneous
     * approved claims on one report impossible at the database level too.
     */
    public function test_item_cannot_be_handed_over_to_two_claimants(): void
    {
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $owner = User::factory()->create();
        $report = $this->foundReport($owner, $community, $category);

        $claimA = Claim::factory()->create([
            'report_id' => $report->id, 'claimant_id' => User::factory()->create()->id, 'status' => 'approved',
        ]);
        $claimB = Claim::factory()->create([
            'report_id' => $report->id, 'claimant_id' => User::factory()->create()->id, 'status' => 'submitted',
        ]);

        // First handover succeeds and resolves the report.
        $this->actingAs($owner)->post(route('claims.complete-handover', $claimA))->assertRedirect();
        $this->assertSame('completed', $claimA->fresh()->status);
        $this->assertSame('resolved', $report->fresh()->status);

        // A second claimant must not be approved for an already-resolved report,
        // nor be able to complete a handover.
        $this->actingAs($owner)->patch(route('claims.review', $claimB), [
            'decision' => 'approved', 'decision_reason' => 'Second winner attempt.',
        ])->assertStatus(422);

        $this->actingAs($owner)->post(route('claims.complete-handover', $claimB))->assertStatus(422);

        $this->assertSame('submitted', $claimB->fresh()->status);
        $this->assertSame(1, ItemEvent::where('report_id', $report->id)
            ->where('action', 'custody_transferred')->count());
    }

    /** D2: the DB itself refuses two winning claims on one report. */
    public function test_database_rejects_two_winning_claims(): void
    {
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $report = $this->foundReport(User::factory()->create(), $community, $category);

        Claim::factory()->create([
            'report_id' => $report->id, 'claimant_id' => User::factory()->create()->id, 'status' => 'approved',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        Claim::factory()->create([
            'report_id' => $report->id, 'claimant_id' => User::factory()->create()->id, 'status' => 'approved',
        ]);
    }

    /** D2: the DB refuses duplicate live claims from the same claimant. */
    public function test_database_rejects_duplicate_live_claims(): void
    {
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $report = $this->foundReport(User::factory()->create(), $community, $category);
        $claimant = User::factory()->create();

        Claim::factory()->create([
            'report_id' => $report->id, 'claimant_id' => $claimant->id, 'status' => 'submitted',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        Claim::factory()->create([
            'report_id' => $report->id, 'claimant_id' => $claimant->id, 'status' => 'submitted',
        ]);
    }

    /** H2: an outsider must not be able to open another community's report. */
    public function test_outsider_cannot_open_report_detail_page(): void
    {
        $community = Community::factory()->university()->create(['name' => 'Secret Community']);
        $category = Category::factory()->create();
        $report = $this->foundReport(User::factory()->create(), $community, $category);

        $outsider = User::factory()->create(); // member of nothing

        $this->actingAs($outsider)->get(route('reports.show', $report))->assertForbidden();
        $this->actingAs($outsider)->get(route('reports.matches', $report))->assertForbidden();
    }

    /** H2: an active member CAN open a report in their own community. */
    public function test_community_member_can_open_report_detail_page(): void
    {
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $report = $this->foundReport(User::factory()->create(), $community, $category);

        $member = User::factory()->create();
        $this->join($member, $community);

        $this->actingAs($member)->get(route('reports.show', $report))->assertOk();
    }

    /** H3: the Livewire wizard rejects a non-image upload. */
    public function test_livewire_wizard_rejects_non_image_photo(): void
    {
        Queue::fake();
        Storage::fake('local');

        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $user = User::factory()->create();
        $this->join($user, $community);

        Livewire::actingAs($user)->test(ReportCreate::class)
            ->set('community_id', $community->id)
            ->set('category_id', $category->id)
            ->set('type', 'lost')
            ->set('title', 'Bad upload')
            ->set('description', 'Testing mime validation.')
            ->set('event_at', now()->subHour()->format('Y-m-d\TH:i'))
            ->set('location_name', 'Gate')
            ->set('photos', [UploadedFile::fake()->create('shell.php', 20, 'application/x-php')])
            ->call('save')
            ->assertHasErrors('photos.0');

        $this->assertSame(0, Report::count());
    }
}
