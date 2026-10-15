<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Claim;
use App\Models\Community;
use App\Models\CommunityMembership;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function foundReport(User $owner, Community $community, Category $category): Report
    {
        return Report::factory()->found()->create([
            'user_id' => $owner->id,
            'community_id' => $community->id,
            'category_id' => $category->id,
            'status' => 'open',
        ]);
    }

    private function reportPayload(Community $community, Category $category): array
    {
        return [
            'community_id' => $community->id,
            'category_id' => $category->id,
            'type' => 'lost',
            'title' => 'Lost backpack',
            'description' => 'A blue backpack with books.',
            'event_at' => now()->subHour()->toDateTimeString(),
            'location_name' => 'Main Gate',
        ];
    }

    public function test_guest_cannot_create_a_report(): void
    {
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();

        $response = $this->post(route('reports.store'), $this->reportPayload($community, $category));

        $response->assertRedirect('/login');
        $this->assertSame(0, Report::count());
    }

    public function test_guest_cannot_submit_a_claim(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $report = $this->foundReport($owner, $community, $category);

        $response = $this->post(route('claims.store', $report), [
            'report_id' => $report->id,
            'answers' => ['q1' => 'answer'],
        ]);

        $response->assertRedirect('/login');
        $this->assertSame(0, Claim::count());
    }

    public function test_non_member_cannot_create_a_report_in_a_community(): void
    {
        $outsider = User::factory()->create();
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($outsider)->post(
            route('reports.store'),
            $this->reportPayload($community, $category)
        );

        $response->assertForbidden();
        $this->assertSame(0, Report::count());
    }

    public function test_suspended_member_cannot_create_a_report(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();

        CommunityMembership::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'role' => 'member',
            'status' => 'suspended',
        ]);

        $response = $this->actingAs($user)->post(
            route('reports.store'),
            $this->reportPayload($community, $category)
        );

        $response->assertForbidden();
        $this->assertSame(0, Report::count());
    }

    public function test_claimant_cannot_review_their_own_claim(): void
    {
        $owner = User::factory()->create();
        $claimant = User::factory()->create();
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $report = $this->foundReport($owner, $community, $category);

        $claim = Claim::factory()->create([
            'report_id' => $report->id,
            'claimant_id' => $claimant->id,
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($claimant)->patch(route('claims.review', $claim), [
            'decision' => 'approved',
            'decision_reason' => 'Self approval attempt.',
        ]);

        $response->assertForbidden();
        $this->assertSame('submitted', $claim->fresh()->status);
    }

    public function test_cross_community_moderator_cannot_review_a_claim(): void
    {
        // Claim lives in community A.
        $owner = User::factory()->create();
        $communityA = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $report = $this->foundReport($owner, $communityA, $category);
        $claim = Claim::factory()->create([
            'report_id' => $report->id,
            'claimant_id' => User::factory()->create()->id,
            'status' => 'submitted',
        ]);

        // A moderator of a DIFFERENT community must not be able to review it.
        $communityB = Community::factory()->university()->create();
        $otherModerator = User::factory()->create();
        CommunityMembership::create([
            'community_id' => $communityB->id,
            'user_id' => $otherModerator->id,
            'role' => 'moderator',
            'status' => 'active',
        ]);

        $response = $this->actingAs($otherModerator)->patch(route('claims.review', $claim), [
            'decision' => 'approved',
            'decision_reason' => 'Not my community.',
        ]);

        $response->assertForbidden();
        $this->assertSame('submitted', $claim->fresh()->status);
    }

    public function test_unrelated_user_cannot_review_another_users_claim(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $report = $this->foundReport($owner, $community, $category);
        $claim = Claim::factory()->create([
            'report_id' => $report->id,
            'claimant_id' => User::factory()->create()->id,
            'status' => 'submitted',
        ]);

        $outsider = User::factory()->create();

        $response = $this->actingAs($outsider)->patch(route('claims.review', $claim), [
            'decision' => 'approved',
            'decision_reason' => 'Should not be allowed.',
        ]);

        $response->assertForbidden();
        $this->assertSame('submitted', $claim->fresh()->status);
    }
}
