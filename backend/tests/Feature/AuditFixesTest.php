<?php

namespace Tests\Feature;

use App\Jobs\GenerateMatches;
use App\Models\Category;
use App\Models\Community;
use App\Models\ItemMatch;
use App\Models\Report;
use App\Models\User;
use App\Notifications\PotentialMatchFound;
use App\Services\MatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Regression tests for the performance and correctness issues found in the audit.
 */
class AuditFixesTest extends TestCase
{
    use RefreshDatabase;

    private function mk(Community $c, Category $cat, string $type, string $title): Report
    {
        return Report::factory()->{$type}()->create([
            'user_id' => User::factory()->create()->id,
            'community_id' => $c->id,
            'category_id' => $cat->id,
            'title' => $title,
            'latitude' => 24.9, 'longitude' => 91.8, 'color' => 'black',
            'description' => 'black leather wallet with cards',
            'event_at' => now()->subHour(),
            'status' => 'open',
            'expires_at' => now()->addDays(30),
        ]);
    }

    /** A8: the notification must reference the OTHER side of the match. */
    public function test_match_notification_references_the_counterpart_report(): void
    {
        Notification::fake();

        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();

        $lost = $this->mk($community, $category, 'lost', 'THE-LOST-ITEM');
        $found = $this->mk($community, $category, 'found', 'THE-FOUND-ITEM');

        (new GenerateMatches($lost->id))->handle(app(MatchingService::class));

        // The owner of the FOUND report should be pointed at the LOST report.
        Notification::assertSentTo(
            $found->user,
            PotentialMatchFound::class,
            function ($notification) use ($found) {
                $data = $notification->toArray($found->user);

                $this->assertSame('THE-LOST-ITEM', $data['report_title']);

                return true;
            }
        );
    }

    /** A6: expired reports must not generate matches. */
    public function test_expired_reports_are_not_matched(): void
    {
        Notification::fake();

        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $lost = $this->mk($community, $category, 'lost', 'LOST');

        $expired = Report::factory()->found()->create([
            'user_id' => User::factory()->create()->id,
            'community_id' => $community->id,
            'category_id' => $category->id,
            'title' => 'STALE-EXPIRED',
            'latitude' => 24.9, 'longitude' => 91.8, 'color' => 'black',
            'description' => 'black leather wallet with cards',
            'event_at' => now()->subYear(),
            'expires_at' => now()->subDays(30),
            'status' => 'open',
        ]);

        (new GenerateMatches($lost->id))->handle(app(MatchingService::class));

        $this->assertSame(
            0,
            ItemMatch::where('found_report_id', $expired->id)->count(),
            'Expired reports must be excluded from matching.'
        );
    }

    /** P1: the dashboard must not issue per-row match-count queries. */
    public function test_dashboard_has_no_n_plus_one_on_match_counts(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();

        Report::factory()->count(10)->create([
            'user_id' => $user->id,
            'community_id' => $community->id,
            'category_id' => $category->id,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $matchQueries = collect(DB::getQueryLog())
            ->filter(fn ($q) => str_contains($q['query'], 'from "matches"'))->count();
        DB::disableQueryLog();

        // withCount() collapses this into a single aggregate query per relation.
        $this->assertLessThanOrEqual(
            2, $matchQueries,
            "Dashboard still issues {$matchQueries} match-count queries (N+1)."
        );
    }

    /** P2: the matching job must eager-load candidate users. */
    public function test_matching_job_has_no_n_plus_one_on_users(): void
    {
        Notification::fake();

        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $lost = $this->mk($community, $category, 'lost', 'LOST');

        foreach (range(1, 8) as $i) {
            $this->mk($community, $category, 'found', "FOUND-{$i}");
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        (new GenerateMatches($lost->id))->handle(app(MatchingService::class));
        $userQueries = collect(DB::getQueryLog())
            ->filter(fn ($q) => str_contains($q['query'], 'from "users"'))->count();
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(
            2, $userQueries,
            "Matching job still issues {$userQueries} user queries (N+1)."
        );
    }
}
