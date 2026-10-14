<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Claim;
use App\Models\Community;
use App\Models\CommunityMembership;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClaimWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function makeFoundReport(User $owner, Community $community, Category $category): Report
    {
        return Report::factory()->found()->create([
            'user_id' => $owner->id,
            'community_id' => $community->id,
            'category_id' => $category->id,
            'status' => 'open',
        ]);
    }

    public function test_authenticated_user_can_submit_claim_on_found_report(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $claimant = User::factory()->create();
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $report = $this->makeFoundReport($owner, $community, $category);

        $response = $this->actingAs($claimant)->post(route('claims.store', $report), [
            'report_id' => $report->id,
            'answers' => ['q1' => 'has a blue case', 'q2' => 'cracked screen'],
            'evidence_path' => UploadedFile::fake()->image('evidence.jpg')->size(1024),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $claim = Claim::first();
        $this->assertNotNull($claim);
        $this->assertSame('submitted', $claim->status);
        $this->assertSame($claimant->id, $claim->claimant_id);
        $this->assertSame($report->id, $claim->report_id);
        $this->assertSame(['q1' => 'has a blue case', 'q2' => 'cracked screen'], $claim->fresh()->answers);

        // Evidence stored privately.
        $this->assertNotNull($claim->evidence_path);
        Storage::disk('local')->assertExists($claim->evidence_path);

        // Audit event recorded.
        $this->assertSame('claim_submitted', $report->events()->first()->action);
    }

    public function test_guest_cannot_submit_claim(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $report = $this->makeFoundReport($owner, $community, $category);

        $response = $this->post(route('claims.store', $report), [
            'report_id' => $report->id,
            'answers' => ['q1' => 'answer'],
        ]);

        $response->assertRedirect('/login');
        $this->assertSame(0, Claim::count());
    }

    public function test_cannot_claim_a_lost_report(): void
    {
        $owner = User::factory()->create();
        $claimant = User::factory()->create();
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();

        $lost = Report::factory()->lost()->create([
            'user_id' => $owner->id,
            'community_id' => $community->id,
            'category_id' => $category->id,
            'status' => 'open',
        ]);

        $response = $this->actingAs($claimant)->post(route('claims.store', $lost), [
            'report_id' => $lost->id,
            'answers' => ['q1' => 'answer'],
        ]);

        // report_id must be a "found" report.
        $response->assertSessionHasErrors('report_id');
        $this->assertSame(0, Claim::count());
    }

    public function test_claim_requires_answers(): void
    {
        $owner = User::factory()->create();
        $claimant = User::factory()->create();
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $report = $this->makeFoundReport($owner, $community, $category);

        $response = $this->actingAs($claimant)->post(route('claims.store', $report), [
            'report_id' => $report->id,
            'answers' => [],
        ]);

        $response->assertSessionHasErrors('answers');
        $this->assertSame(0, Claim::count());
    }

    private function makeClaim(array $overrides = []): Claim
    {
        $owner = User::factory()->create();
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $report = $this->makeFoundReport($owner, $community, $category);
        $claimant = User::factory()->create();

        return Claim::factory()->create($overrides + [
            'report_id' => $report->id,
            'claimant_id' => $claimant->id,
        ]);
    }

    public function test_found_reporter_can_review_claim(): void
    {
        $claim = $this->makeClaim();
        $reporter = $claim->report->user;

        $response = $this->actingAs($reporter)->patch(route('claims.review', $claim), [
            'decision' => 'approved',
            'decision_reason' => 'Answered all questions correctly.',
        ]);

        $response->assertRedirect();
        $claim->refresh();
        $this->assertSame('approved', $claim->status);
        $this->assertSame($reporter->id, $claim->reviewed_by);
        $this->assertNotNull($claim->reviewed_at);
        $this->assertSame('claim_reviewed', $claim->report->events()->where('action', 'claim_reviewed')->first()->action);
    }

    public function test_active_moderator_can_review_claim(): void
    {
        $claim = $this->makeClaim();
        $community = $claim->report->community;
        $moderator = User::factory()->create();

        CommunityMembership::create([
            'community_id' => $community->id,
            'user_id' => $moderator->id,
            'role' => 'moderator',
            'status' => 'active',
        ]);

        $response = $this->actingAs($moderator)->patch(route('claims.review', $claim), [
            'decision' => 'rejected',
            'decision_reason' => 'Insufficient verification.',
        ]);

        $response->assertRedirect();
        $claim->refresh();
        $this->assertSame('rejected', $claim->status);
    }

    public function test_claimant_cannot_review_own_claim(): void
    {
        $claim = $this->makeClaim();

        $response = $this->actingAs($claim->claimant)->patch(route('claims.review', $claim), [
            'decision' => 'approved',
            'decision_reason' => 'Self approve.',
        ]);

        $response->assertForbidden();
        $this->assertSame('submitted', $claim->fresh()->status);
    }

    public function test_unrelated_user_cannot_review_claim(): void
    {
        $claim = $this->makeClaim();
        $outsider = User::factory()->create();

        $response = $this->actingAs($outsider)->patch(route('claims.review', $claim), [
            'decision' => 'approved',
            'decision_reason' => 'Nope.',
        ]);

        $response->assertForbidden();
        $this->assertSame('submitted', $claim->fresh()->status);
    }

    public function test_suspended_moderator_cannot_review_claim(): void
    {
        $claim = $this->makeClaim();
        $community = $claim->report->community;
        $moderator = User::factory()->create();

        CommunityMembership::create([
            'community_id' => $community->id,
            'user_id' => $moderator->id,
            'role' => 'moderator',
            'status' => 'suspended',
        ]);

        $response = $this->actingAs($moderator)->patch(route('claims.review', $claim), [
            'decision' => 'approved',
            'decision_reason' => 'Nope.',
        ]);

        $response->assertForbidden();
        $this->assertSame('submitted', $claim->fresh()->status);
    }

    public function test_completing_handover_marks_claim_completed_and_report_resolved(): void
    {
        $claim = $this->makeClaim(['status' => 'approved']);
        $report = $claim->report;
        $reporter = $report->user;

        $response = $this->actingAs($reporter)->post(route('claims.complete-handover', $claim));

        $response->assertRedirect();
        $claim->refresh();
        $report->refresh();

        $this->assertSame('completed', $claim->status);
        $this->assertSame('resolved', $report->status);
        $this->assertSame('custody_transferred', $report->events()->where('action', 'custody_transferred')->first()->action);
    }

    public function test_cannot_complete_handover_unless_approved(): void
    {
        $claim = $this->makeClaim(['status' => 'submitted']);
        $reporter = $claim->report->user;

        $response = $this->actingAs($reporter)->post(route('claims.complete-handover', $claim));

        $response->assertStatus(422);
        $this->assertSame('submitted', $claim->fresh()->status);
        $this->assertSame('open', $claim->report->fresh()->status);
    }

    public function test_claimant_cannot_complete_handover(): void
    {
        $claim = $this->makeClaim(['status' => 'approved']);

        $response = $this->actingAs($claim->claimant)->post(route('claims.complete-handover', $claim));

        $response->assertForbidden();
        $this->assertSame('approved', $claim->fresh()->status);
    }
}
