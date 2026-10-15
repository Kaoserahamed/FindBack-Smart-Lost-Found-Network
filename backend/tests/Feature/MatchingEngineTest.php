<?php

namespace Tests\Feature;

use App\Jobs\GenerateMatches;
use App\Models\Category;
use App\Models\Community;
use App\Models\ItemMatch;
use App\Models\Report;
use App\Models\User;
use App\Services\MatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MatchingEngineTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Attributes that yield a strong match (same area, close in time, same
     * color, similar description) so the score clears the >= 40 threshold.
     */
    private function strongAttrs(): array
    {
        return [
            'latitude' => 24.9000,
            'longitude' => 91.8000,
            'color' => 'black',
        ];
    }

    public function test_valid_lost_and_found_reports_in_same_community_and_category_generate_a_match(): void
    {
        Notification::fake();

        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();

        $lost = Report::factory()->lost()->create($this->strongAttrs() + [
            'user_id' => User::factory()->create()->id,
            'community_id' => $community->id,
            'category_id' => $category->id,
            'description' => 'black leather wallet with cards',
            'event_at' => now()->subHour(),
            'status' => 'open',
        ]);

        $found = Report::factory()->found()->create($this->strongAttrs() + [
            'user_id' => User::factory()->create()->id,
            'community_id' => $community->id,
            'category_id' => $category->id,
            'description' => 'black leather wallet with cards',
            'event_at' => now()->subHours(2),
            'status' => 'open',
        ]);

        GenerateMatches::dispatch($lost->id);

        $this->assertSame(1, ItemMatch::count());
        $match = ItemMatch::first();
        $this->assertSame($lost->id, $match->lost_report_id);
        $this->assertSame($found->id, $match->found_report_id);
        $this->assertGreaterThanOrEqual(MatchingService::MIN_SCORE, $match->score);
        $this->assertSame('pending', $match->status);
        $this->assertNotEmpty($match->reasons);
    }

    public function test_cross_community_reports_do_not_generate_matches(): void
    {
        Notification::fake();

        $communityA = Community::factory()->university()->create();
        $communityB = Community::factory()->university()->create();
        $category = Category::factory()->create();

        // Identical attributes but in different communities.
        $lost = Report::factory()->lost()->create($this->strongAttrs() + [
            'user_id' => User::factory()->create()->id,
            'community_id' => $communityA->id,
            'category_id' => $category->id,
            'description' => 'black leather wallet with cards',
            'event_at' => now()->subHour(),
            'status' => 'open',
        ]);

        Report::factory()->found()->create($this->strongAttrs() + [
            'user_id' => User::factory()->create()->id,
            'community_id' => $communityB->id, // different community
            'category_id' => $category->id,
            'description' => 'black leather wallet with cards',
            'event_at' => now()->subHours(2),
            'status' => 'open',
        ]);

        GenerateMatches::dispatch($lost->id);

        $this->assertSame(0, ItemMatch::count());
        Notification::assertNothingSent();
    }

    public function test_running_the_job_multiple_times_does_not_create_duplicate_matches(): void
    {
        Notification::fake();

        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();

        $lost = Report::factory()->lost()->create($this->strongAttrs() + [
            'user_id' => User::factory()->create()->id,
            'community_id' => $community->id,
            'category_id' => $category->id,
            'description' => 'black leather wallet with cards',
            'event_at' => now()->subHour(),
            'status' => 'open',
        ]);

        Report::factory()->found()->create($this->strongAttrs() + [
            'user_id' => User::factory()->create()->id,
            'community_id' => $community->id,
            'category_id' => $category->id,
            'description' => 'black leather wallet with cards',
            'event_at' => now()->subHours(2),
            'status' => 'open',
        ]);

        // Simulate several retries of the queued job.
        GenerateMatches::dispatch($lost->id);
        GenerateMatches::dispatch($lost->id);
        GenerateMatches::dispatch($lost->id);

        // The unique [lost_report_id, found_report_id] constraint guarantees a
        // single row regardless of how many times the job runs.
        $this->assertSame(1, ItemMatch::count());
    }
}
