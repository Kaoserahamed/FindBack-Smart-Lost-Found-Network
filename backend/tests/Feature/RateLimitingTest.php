<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Community;
use App\Models\CommunityMembership;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    /** H1: the login endpoint must throttle brute-force attempts. */
    public function test_login_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'a@b.com', 'password' => bcrypt('correct-horse')]);

        $statuses = [];
        for ($i = 0; $i < 12; $i++) {
            $statuses[] = $this->post(route('login.attempt'), [
                'email' => 'a@b.com', 'password' => "wrong-{$i}",
            ])->getStatusCode();
        }

        $this->assertContains(
            429, $statuses,
            'Expected the login endpoint to throttle after repeated failures, got: '.implode(',', $statuses)
        );
    }

    /** H1: claim submission must be throttled. */
    public function test_claim_submission_is_rate_limited(): void
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

        $statuses = [];
        for ($i = 0; $i < 14; $i++) {
            $statuses[] = $this->actingAs($claimant)->post(route('claims.store', $report), [
                'report_id' => $report->id,
                'answers' => ['q1' => "attempt {$i}"],
            ])->getStatusCode();
        }

        $this->assertContains(
            429, $statuses,
            'Expected claim submission to throttle, got: '.implode(',', $statuses)
        );
    }
}
