<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Claim;
use App\Models\Community;
use App\Models\CommunityMembership;
use App\Models\ItemEvent;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HandoverConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(string $claimStatus = 'submitted'): array
    {
        $reporter = User::factory()->create();
        $claimant = User::factory()->create();
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();

        $report = Report::factory()->found()->create([
            'user_id' => $reporter->id,
            'community_id' => $community->id,
            'category_id' => $category->id,
            'status' => 'open',
        ]);

        $claim = Claim::factory()->create([
            'report_id' => $report->id,
            'claimant_id' => $claimant->id,
            'status' => $claimStatus,
        ]);

        return [$reporter, $claimant, $report, $claim];
    }

    public function test_claim_store_records_private_answers_and_private_evidence(): void
    {
        Storage::fake('local');

        $reporter = User::factory()->create();
        $claimant = User::factory()->create();
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $report = Report::factory()->found()->create([
            'user_id' => $reporter->id,
            'community_id' => $community->id,
            'category_id' => $category->id,
            'status' => 'open',
        ]);

        // The claimant must be an active member of the report's community.
        CommunityMembership::create([
            'community_id' => $community->id,
            'user_id' => $claimant->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        $answers = ['serial' => 'SN-12345', 'mark' => 'scratch on corner'];

        $this->actingAs($claimant)->post(route('claims.store', $report), [
            'report_id' => $report->id,
            'answers' => $answers,
            'evidence_path' => UploadedFile::fake()->image('proof.jpg')->size(1024),
        ])->assertRedirect();

        $claim = Claim::first();
        // Private verification answers are persisted (and cast back to array).
        // jsonb does not guarantee key order, so compare values individually.
        $stored = $claim->fresh()->answers;
        $this->assertSame('SN-12345', $stored['serial']);
        $this->assertSame('scratch on corner', $stored['mark']);

        // Evidence is stored in the PRIVATE disk and never publicly exposed.
        $this->assertNotNull($claim->evidence_path);
        Storage::disk('local')->assertExists($claim->evidence_path);
        Storage::disk('public')->assertMissing($claim->evidence_path);
    }

    public function test_handover_resolves_report_and_logs_custody_event(): void
    {
        [$reporter, $claimant, $report, $claim] = $this->scenario('approved');

        $response = $this->actingAs($reporter)->post(route('claims.complete-handover', $claim));

        $response->assertRedirect();
        $claim->refresh();
        $report->refresh();

        $this->assertSame('completed', $claim->status);
        $this->assertSame('resolved', $report->status);

        // A single custody_transferred event is logged against the report.
        $events = ItemEvent::where('report_id', $report->id)
            ->where('action', 'custody_transferred')
            ->get();
        $this->assertCount(1, $events);
        $this->assertSame($claim->id, $events->first()->metadata['claim_id']);
        $this->assertSame($claimant->id, $events->first()->metadata['to_user_id']);
    }

    public function test_handover_is_rejected_unless_the_claim_is_approved(): void
    {
        [, , $report, $claim] = $this->scenario('submitted');
        $reporter = $report->user;

        $response = $this->actingAs($reporter)->post(route('claims.complete-handover', $claim));

        $response->assertStatus(422);
        $this->assertSame('submitted', $claim->fresh()->status);
        $this->assertSame('open', $report->fresh()->status);
        $this->assertSame(0, ItemEvent::where('action', 'custody_transferred')->count());
    }

    public function test_handover_uses_a_row_lock(): void
    {
        [, , $report, $claim] = $this->scenario('approved');

        // Prove the finalization takes a row-level lock by inspecting the SQL.
        DB::enableQueryLog();

        $this->actingAs($report->user)->post(route('claims.complete-handover', $claim))
            ->assertRedirect();

        $usedForUpdate = collect(DB::getQueryLog())
            ->contains(fn ($q) => str_contains(strtolower($q['query']), 'for update'));

        $this->assertTrue(
            $usedForUpdate,
            'Expected the handover to issue a SELECT ... FOR UPDATE row lock.'
        );
    }

    public function test_concurrent_handover_attempts_complete_only_once(): void
    {
        [, , $report, $claim] = $this->scenario('approved');
        $reporter = $report->user;

        // First handover succeeds.
        $this->actingAs($reporter)->post(route('claims.complete-handover', $claim))
            ->assertRedirect();

        // A racing second handover is rejected by the state guard (the claim is
        // no longer "approved"), so no duplicate custody event is written.
        $this->actingAs($reporter)->post(route('claims.complete-handover', $claim))
            ->assertStatus(422);

        $this->assertSame('completed', $claim->fresh()->status);
        $this->assertSame('resolved', $report->fresh()->status);
        $this->assertSame(
            1,
            ItemEvent::where('report_id', $report->id)
                ->where('action', 'custody_transferred')
                ->count()
        );
    }
}
