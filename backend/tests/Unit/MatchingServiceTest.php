<?php

namespace Tests\Unit;

use App\Models\Report;
use App\Services\MatchingService;
use Tests\TestCase;

class MatchingServiceTest extends TestCase
{
    private MatchingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MatchingService();
    }

    private function makeReport(array $overrides = []): Report
    {
        return new Report(array_merge([
            'community_id' => 1,
            'category_id' => 1,
            'type' => 'lost',
            'description' => 'black leather wallet',
            'color' => null,
            'latitude' => null,
            'longitude' => null,
            'event_at' => now(),
        ], $overrides));
    }

    public function test_returns_zero_when_community_differs(): void
    {
        $a = $this->makeReport(['type' => 'lost']);
        $b = $this->makeReport(['type' => 'found', 'community_id' => 2]);

        $this->assertSame(0, $this->service->score($a, $b)['score']);
    }

    public function test_returns_zero_when_category_differs(): void
    {
        $a = $this->makeReport(['type' => 'lost']);
        $b = $this->makeReport(['type' => 'found', 'category_id' => 2]);

        $this->assertSame(0, $this->service->score($a, $b)['score']);
    }

    public function test_returns_zero_when_types_are_the_same(): void
    {
        $a = $this->makeReport(['type' => 'lost']);
        $b = $this->makeReport(['type' => 'lost']);

        $this->assertSame(0, $this->service->score($a, $b)['score']);
    }

    public function test_perfect_match_scores_100_with_reasons(): void
    {
        $a = $this->makeReport([
            'type' => 'lost',
            'description' => 'black leather wallet with cards',
            'color' => 'Black',
            'latitude' => 24.9000,
            'longitude' => 91.8000,
            'event_at' => now()->subHour(),
        ]);
        $b = $this->makeReport([
            'type' => 'found',
            'description' => 'black leather wallet with cards',
            'color' => 'black',
            'latitude' => 24.9000,
            'longitude' => 91.8000,
            'event_at' => now()->subHours(2),
        ]);

        $result = $this->service->score($a, $b);

        $this->assertSame(100, $result['score']);
        $this->assertContains('Same category', $result['reasons']);
        $this->assertContains('Matching color: Black', $result['reasons']);
        $this->assertTrue(
            collect($result['reasons'])->contains(fn ($r) => str_contains($r, 'km apart'))
        );
    }

    public function test_category_alone_keeps_score_below_threshold(): void
    {
        // No coords, >72h apart, different color, dissimilar descriptions.
        $a = $this->makeReport(['type' => 'lost', 'description' => 'aaaa', 'color' => null, 'event_at' => now()->subHours(200)]);
        $b = $this->makeReport(['type' => 'found', 'description' => 'zzzz', 'color' => null, 'event_at' => now()]);

        $result = $this->service->score($a, $b);

        $this->assertSame(30, $result['score']);
        $this->assertLessThan(MatchingService::MIN_SCORE, $result['score']);
        $this->assertContains('Same category', $result['reasons']);
    }

    public function test_time_proximity_tiers(): void
    {
        // Base (category 30 + identical description 15) = 45.
        $cases = [
            'within_6h' => [3, 20],
            'within_24h' => [20, 15],
            'within_72h' => [50, 8],
            'beyond_72h' => [100, 0],
        ];

        foreach ($cases as [$hoursAgo, $timePoints]) {
            $a = $this->makeReport(['type' => 'lost', 'event_at' => now()->subHours($hoursAgo)]);
            $b = $this->makeReport(['type' => 'found', 'event_at' => now()]);

            $this->assertSame(45 + $timePoints, $this->service->score($a, $b)['score']);
        }
    }

    public function test_geographic_proximity_scoring(): void
    {
        // Close together (same area) -> full 25 geo points; base 45.
        $a = $this->makeReport(['type' => 'lost', 'latitude' => 24.9000, 'longitude' => 91.8000, 'event_at' => now()->subHours(200)]);
        $b = $this->makeReport(['type' => 'found', 'latitude' => 24.9001, 'longitude' => 91.8001, 'event_at' => now()]);
        $this->assertSame(70, $this->service->score($a, $b)['score']);

        // Far apart -> no geo points; base 45.
        $c = $this->makeReport(['type' => 'found', 'latitude' => 40.7128, 'longitude' => -74.0060, 'event_at' => now()]);
        $this->assertSame(45, $this->service->score($a, $c)['score']);
    }

    public function test_color_matching_is_case_insensitive(): void
    {
        $a = $this->makeReport(['type' => 'lost', 'color' => 'Black', 'event_at' => now()->subHours(200)]);
        $b = $this->makeReport(['type' => 'found', 'color' => 'black', 'event_at' => now()]);
        $this->assertSame(55, $this->service->score($a, $b)['score']);

        $c = $this->makeReport(['type' => 'found', 'color' => 'red', 'event_at' => now()]);
        $this->assertSame(45, $this->service->score($a, $c)['score']);
    }
}
