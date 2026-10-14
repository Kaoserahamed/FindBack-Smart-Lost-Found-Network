<?php

namespace Tests\Feature;

use App\Jobs\GenerateMatches;
use App\Models\Category;
use App\Models\Community;
use App\Models\ItemMatch;
use App\Models\Report;
use App\Models\User;
use App\Notifications\MatchFound;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GenerateMatchesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Attributes that make two reports a strong match (same area, close in
     * time, same color, similar description) => score well above threshold.
     */
    private function strongMatchAttrs(): array
    {
        return [
            'latitude' => 24.9000,
            'longitude' => 91.8000,
            'color' => 'black',
        ];
    }

    public function test_creates_match_above_threshold_and_notifies_counterparty(): void
    {
        Notification::fake();

        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $owner = User::factory()->create();
        $finder = User::factory()->create();

        $lost = Report::factory()->lost()->create($this->strongMatchAttrs() + [
            'user_id' => $owner->id,
            'community_id' => $community->id,
            'category_id' => $category->id,
            'description' => 'black leather wallet with cards',
            'event_at' => now()->subHour(),
        ]);

        $found = Report::factory()->found()->create($this->strongMatchAttrs() + [
            'user_id' => $finder->id,
            'community_id' => $community->id,
            'category_id' => $category->id,
            'description' => 'black leather wallet with cards',
            'event_at' => now()->subHours(2),
        ]);

        GenerateMatches::dispatch($lost->id);

        $this->assertSame(1, ItemMatch::count());
        $match = ItemMatch::first();
        $this->assertSame($lost->id, $match->lost_report_id);
        $this->assertSame($found->id, $match->found_report_id);
        $this->assertGreaterThanOrEqual(40, $match->score);
        $this->assertSame('pending', $match->status);
        $this->assertNotEmpty($match->reasons);

        Notification::assertSentTo($finder, MatchFound::class);
        Notification::assertNotSentTo($owner, MatchFound::class);
    }

    public function test_does_not_create_match_below_threshold(): void
    {
        Notification::fake();

        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();

        $lost = Report::factory()->lost()->create([
            'user_id' => User::factory()->create()->id,
            'community_id' => $community->id,
            'category_id' => $category->id,
            'description' => 'aaaa',
            'color' => null,
            'latitude' => null,
            'longitude' => null,
            'event_at' => now()->subHours(200),
        ]);

        Report::factory()->found()->create([
            'user_id' => User::factory()->create()->id,
            'community_id' => $community->id,
            'category_id' => $category->id,
            'description' => 'zzzz',
            'color' => null,
            'latitude' => null,
            'longitude' => null,
            'event_at' => now(),
        ]);

        GenerateMatches::dispatch($lost->id);

        $this->assertSame(0, ItemMatch::count());
        Notification::assertNothingSent();
    }

    public function test_ignores_reports_that_are_not_open(): void
    {
        Notification::fake();

        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();

        $lost = Report::factory()->lost()->create($this->strongMatchAttrs() + [
            'user_id' => User::factory()->create()->id,
            'community_id' => $community->id,
            'category_id' => $category->id,
            'status' => 'resolved',
            'description' => 'black leather wallet',
        ]);

        Report::factory()->found()->create($this->strongMatchAttrs() + [
            'user_id' => User::factory()->create()->id,
            'community_id' => $community->id,
            'category_id' => $category->id,
            'description' => 'black leather wallet',
        ]);

        GenerateMatches::dispatch($lost->id);

        $this->assertSame(0, ItemMatch::count());
        Notification::assertNothingSent();
    }

    public function test_ignores_candidates_in_other_community_or_category(): void
    {
        $communityA = Community::factory()->university()->create();
        $communityB = Community::factory()->university()->create();
        $categoryA = Category::factory()->create();
        $categoryB = Category::factory()->create();

        $lost = Report::factory()->lost()->create($this->strongMatchAttrs() + [
            'user_id' => User::factory()->create()->id,
            'community_id' => $communityA->id,
            'category_id' => $categoryA->id,
            'description' => 'black leather wallet',
        ]);

        Report::factory()->found()->create($this->strongMatchAttrs() + [
            'user_id' => User::factory()->create()->id,
            'community_id' => $communityB->id,
            'category_id' => $categoryB->id,
            'description' => 'black leather wallet',
        ]);

        GenerateMatches::dispatch($lost->id);

        $this->assertSame(0, ItemMatch::count());
    }

    public function test_is_idempotent_on_retry(): void
    {
        Notification::fake();

        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $finder = User::factory()->create();

        $lost = Report::factory()->lost()->create($this->strongMatchAttrs() + [
            'user_id' => User::factory()->create()->id,
            'community_id' => $community->id,
            'category_id' => $category->id,
            'description' => 'black leather wallet with cards',
        ]);

        Report::factory()->found()->create($this->strongMatchAttrs() + [
            'user_id' => $finder->id,
            'community_id' => $community->id,
            'category_id' => $category->id,
            'description' => 'black leather wallet with cards',
        ]);

        GenerateMatches::dispatch($lost->id);
        GenerateMatches::dispatch($lost->id); // simulate a retry

        $this->assertSame(1, ItemMatch::count());
        Notification::assertSentTimes(MatchFound::class, 1);
    }

    public function test_orientation_is_normalized_when_triggered_by_found_report(): void
    {
        Notification::fake();

        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $owner = User::factory()->create();

        $lost = Report::factory()->lost()->create($this->strongMatchAttrs() + [
            'user_id' => $owner->id,
            'community_id' => $community->id,
            'category_id' => $category->id,
            'description' => 'black leather wallet with cards',
        ]);

        $found = Report::factory()->found()->create($this->strongMatchAttrs() + [
            'user_id' => User::factory()->create()->id,
            'community_id' => $community->id,
            'category_id' => $category->id,
            'description' => 'black leather wallet with cards',
        ]);

        // Trigger from the FOUND report; orientation must still be lost=lost.id.
        GenerateMatches::dispatch($found->id);

        $match = ItemMatch::first();
        $this->assertSame($lost->id, $match->lost_report_id);
        $this->assertSame($found->id, $match->found_report_id);
        Notification::assertSentTo($owner, MatchFound::class);
    }
}