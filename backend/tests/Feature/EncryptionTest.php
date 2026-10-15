<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Claim;
use App\Models\Community;
use App\Models\CommunityMembership;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EncryptionTest extends TestCase
{
    use RefreshDatabase;

    /** C3: private verification answers must not be readable in the raw column. */
    public function test_claim_answers_are_encrypted_at_rest(): void
    {
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $owner = User::factory()->create();
        $report = Report::factory()->found()->create([
            'user_id' => $owner->id,
            'community_id' => $community->id,
            'category_id' => $category->id,
            'status' => 'open',
        ]);

        $claimant = User::factory()->create();
        CommunityMembership::create([
            'community_id' => $community->id, 'user_id' => $claimant->id,
            'role' => 'member', 'status' => 'active',
        ]);

        $secret = 'serial-number-ABC-123-XYZ';

        $this->actingAs($claimant)->post(route('claims.store', $report), [
            'report_id' => $report->id,
            'answers' => ['serial' => $secret],
        ])->assertRedirect();

        $raw = (string) DB::table('claims')->value('answers');

        // The secret must NOT appear in plaintext in the database.
        $this->assertStringNotContainsString(
            $secret,
            $raw,
            'Private verification answers are stored as readable plaintext.'
        );

        // It must still be transparently readable through the model.
        $this->assertSame($secret, Claim::first()->answers['serial']);
    }

    /** The review UI must still be able to display the decrypted answers. */
    public function test_reviewer_can_still_read_answers(): void
    {
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $owner = User::factory()->create();
        $report = Report::factory()->found()->create([
            'user_id' => $owner->id,
            'community_id' => $community->id,
            'category_id' => $category->id,
            'status' => 'open',
        ]);

        $claim = Claim::factory()->create([
            'report_id' => $report->id,
            'claimant_id' => User::factory()->create()->id,
            'status' => 'submitted',
            'answers' => ['serial' => 'SN-999'],
        ]);

        $this->assertSame('SN-999', $claim->fresh()->answers['serial']);
    }

    /** The backfill command must encrypt legacy plaintext rows exactly once. */
    public function test_backfill_command_encrypts_legacy_plaintext(): void
    {
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $report = Report::factory()->found()->create([
            'user_id' => User::factory()->create()->id,
            'community_id' => $community->id,
            'category_id' => $category->id,
            'status' => 'open',
        ]);

        $claim = Claim::factory()->create([
            'report_id' => $report->id,
            'claimant_id' => User::factory()->create()->id,
            'status' => 'submitted',
            'answers' => ['serial' => 'LEGACY-PLAINTEXT'],
        ]);

        // Simulate a row written before the encrypted cast existed.
        DB::table('claims')->where('id', $claim->id)
            ->update(['answers' => json_encode(['serial' => 'LEGACY-PLAINTEXT'])]);

        $this->assertStringContainsString(
            'LEGACY-PLAINTEXT',
            (string) DB::table('claims')->where('id', $claim->id)->value('answers')
        );

        $this->artisan('claims:encrypt-answers')->assertSuccessful();

        // No longer readable in the raw column...
        $this->assertStringNotContainsString(
            'LEGACY-PLAINTEXT',
            (string) DB::table('claims')->where('id', $claim->id)->value('answers')
        );

        // ...but still transparently readable through the model.
        $this->assertSame('LEGACY-PLAINTEXT', $claim->fresh()->answers['serial']);

        // Running it again must not double-encrypt.
        $this->artisan('claims:encrypt-answers')->assertSuccessful();
        $this->assertSame('LEGACY-PLAINTEXT', $claim->fresh()->answers['serial']);
    }
}
