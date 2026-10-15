<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Claim;
use App\Models\Community;
use App\Models\ItemMatch;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ClaimStatusUpdated;
use App\Notifications\PotentialMatchFound;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_potential_match_found_supports_database_and_mail_channels(): void
    {
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $user = User::factory()->create();

        $lost = Report::factory()->lost()->create([
            'user_id' => $user->id, 'community_id' => $community->id, 'category_id' => $category->id,
            'title' => 'Lost wallet',
        ]);
        $found = Report::factory()->found()->create([
            'user_id' => User::factory()->create()->id, 'community_id' => $community->id, 'category_id' => $category->id,
            'title' => 'Found wallet',
        ]);
        $match = ItemMatch::create([
            'lost_report_id' => $lost->id, 'found_report_id' => $found->id,
            'score' => 82, 'reasons' => ['Same category'], 'status' => 'pending', 'algorithm_version' => 'v1.0',
        ]);

        $notification = new PotentialMatchFound($match);

        $this->assertSame(['database', 'mail'], $notification->via($user));

        $array = $notification->toArray($user);
        $this->assertSame($match->id, $array['match_id']);
        $this->assertSame(82, $array['score']);
        $this->assertArrayHasKey('summary', $array);

        // Mail channel renders a subject without error.
        $mail = $notification->toMail($user);
        $this->assertNotNull($mail->subject);
    }

    public function test_claim_status_updated_supports_database_and_mail_channels(): void
    {
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $report = Report::factory()->found()->create([
            'user_id' => User::factory()->create()->id, 'community_id' => $community->id, 'category_id' => $category->id,
            'title' => 'Found keys',
        ]);
        $claim = Claim::factory()->approved()->create([
            'report_id' => $report->id,
            'claimant_id' => User::factory()->create()->id,
        ]);
        $claimant = $claim->claimant;

        $notification = new ClaimStatusUpdated($claim);

        $this->assertSame(['database', 'mail'], $notification->via($claimant));

        $array = $notification->toArray($claimant);
        $this->assertSame($claim->id, $array['claim_id']);
        $this->assertSame('approved', $array['status']);
        $this->assertArrayHasKey('summary', $array);

        $mail = $notification->toMail($claimant);
        $this->assertNotNull($mail->subject);
    }

    public function test_reviewing_a_claim_notifies_the_claimant(): void
    {
        Notification::fake();

        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $reporter = User::factory()->create();
        $claimant = User::factory()->create();
        $report = Report::factory()->found()->create([
            'user_id' => $reporter->id, 'community_id' => $community->id, 'category_id' => $category->id,
            'status' => 'open',
        ]);
        $claim = Claim::factory()->create([
            'report_id' => $report->id, 'claimant_id' => $claimant->id, 'status' => 'submitted',
        ]);

        $this->actingAs($reporter)->patch(route('claims.review', $claim), [
            'decision' => 'approved',
            'decision_reason' => 'Verified.',
        ])->assertRedirect();

        Notification::assertSentTo($claimant, ClaimStatusUpdated::class);
    }

    public function test_rejecting_a_claim_notifies_the_claimant(): void
    {
        Notification::fake();

        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $reporter = User::factory()->create();
        $claimant = User::factory()->create();
        $report = Report::factory()->found()->create([
            'user_id' => $reporter->id, 'community_id' => $community->id, 'category_id' => $category->id,
            'status' => 'open',
        ]);
        $claim = Claim::factory()->create([
            'report_id' => $report->id, 'claimant_id' => $claimant->id, 'status' => 'submitted',
        ]);

        $this->actingAs($reporter)->patch(route('claims.review', $claim), [
            'decision' => 'rejected',
            'decision_reason' => 'Could not verify ownership.',
        ])->assertRedirect();

        Notification::assertSentTo($claimant, ClaimStatusUpdated::class);
    }
}
