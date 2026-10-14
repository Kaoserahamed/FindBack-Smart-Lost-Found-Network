<?php

namespace App\Services;

use App\Models\Report;

class MatchingService
{
    /**
     * Current algorithm version, stamped onto generated matches.
     */
    public const ALGORITHM_VERSION = 'v1.0';

    /**
     * Minimum score required for a candidate to be persisted as a match.
     */
    public const MIN_SCORE = 40;

    /**
     * Weights for each criterion. They sum to 100 so a perfect match scores 100.
     */
    private const WEIGHT_CATEGORY = 30;
    private const WEIGHT_GEOGRAPHY = 25;
    private const WEIGHT_TIME = 20;
    private const WEIGHT_COLOR = 10;
    private const WEIGHT_DESCRIPTION = 15;

    /**
     * Geographic scoring configuration (kilometres).
     */
    private const GEO_FULL_RADIUS_KM = 1.0;
    private const GEO_MAX_RADIUS_KM = 10.0;
    private const EARTH_RADIUS_KM = 6371.0;

    /**
     * Time-proximity tiers: max hours => points awarded.
     *
     * @var array<int, array{hours: int, points: int}>
     */
    private const TIME_WINDOWS = [
        ['hours' => 6, 'points' => 20],
        ['hours' => 24, 'points' => 15],
        ['hours' => 72, 'points' => 8],
    ];

    /**
     * Score how well two reports match.
     *
     * Returns an array with an integer `score` (0-100) and a `reasons` array of
     * human-readable strings explaining the score. Strict gates (same community,
     * same category, opposite types) short-circuit to a score of 0.
     *
     * @return array{score: int, reasons: array<int, string>}
     */
    public function score(Report $a, Report $b): array
    {
        if (! $this->passesStrictChecks($a, $b)) {
            return ['score' => 0, 'reasons' => []];
        }

        $reasons = [];
        $score = 0.0;

        // Category - guaranteed by the strict gate above.
        $score += self::WEIGHT_CATEGORY;
        $reasons[] = 'Same category';

        // Geographic proximity (Haversine).
        $score += $this->geographyPoints($a, $b, $reasons);

        // Time proximity based on event_at.
        $score += $this->timePoints($a, $b, $reasons);

        // Case-insensitive color match.
        $score += $this->colorPoints($a, $b, $reasons);

        // Description similarity via similar_text().
        $score += $this->descriptionPoints($a, $b, $reasons);

        return [
            'score' => min(100, (int) round($score)),
            'reasons' => $reasons,
        ];
    }

    /**
     * Strict gates: same community, same category, opposite (valid) types.
     */
    protected function passesStrictChecks(Report $a, Report $b): bool
    {
        return $a->community_id === $b->community_id
            && $a->category_id === $b->category_id
            && in_array($a->type, ['lost', 'found'], true)
            && in_array($b->type, ['lost', 'found'], true)
            && $a->type !== $b->type;
    }

    /**
     * Award points for geographic proximity (0-25), if coordinates are present.
     *
     * @param  array<int, string>  $reasons
     */
    protected function geographyPoints(Report $a, Report $b, array &$reasons): float
    {
        if (! $this->hasCoordinates($a) || ! $this->hasCoordinates($b)) {
            return 0.0;
        }

        $distance = $this->haversineKm(
            (float) $a->latitude,
            (float) $a->longitude,
            (float) $b->latitude,
            (float) $b->longitude,
        );

        if ($distance >= self::GEO_MAX_RADIUS_KM) {
            return 0.0;
        }

        if ($distance <= self::GEO_FULL_RADIUS_KM) {
            $points = self::WEIGHT_GEOGRAPHY;
        } else {
            $span = self::GEO_MAX_RADIUS_KM - self::GEO_FULL_RADIUS_KM;
            $ratio = 1.0 - (($distance - self::GEO_FULL_RADIUS_KM) / $span);
            $points = self::WEIGHT_GEOGRAPHY * max(0.0, min(1.0, $ratio));
        }

        $reasons[] = sprintf('Located about %.1f km apart', $distance);

        return $points;
    }

    /**
     * Award points for time proximity based on event_at (0-20).
     *
     * @param  array<int, string>  $reasons
     */
    protected function timePoints(Report $a, Report $b, array &$reasons): int
    {
        if (! $a->event_at || ! $b->event_at) {
            return 0;
        }

        $hours = (float) abs($a->event_at->diffInHours($b->event_at));

        foreach (self::TIME_WINDOWS as $window) {
            if ($hours <= $window['hours']) {
                $reasons[] = sprintf('Events within %d hours', $window['hours']);

                return $window['points'];
            }
        }

        return 0;
    }

    /**
     * Award points for a case-insensitive color match (0-10).
     *
     * @param  array<int, string>  $reasons
     */
    protected function colorPoints(Report $a, Report $b, array &$reasons): int
    {
        if ($a->color && $b->color && strcasecmp(trim($a->color), trim($b->color)) === 0) {
            $reasons[] = 'Matching color: '.$a->color;

            return self::WEIGHT_COLOR;
        }

        return 0;
    }

    /**
     * Award points for description similarity using similar_text() (0-15).
     *
     * @param  array<int, string>  $reasons
     */
    protected function descriptionPoints(Report $a, Report $b, array &$reasons): int
    {
        $percent = 0.0;
        similar_text((string) $a->description, (string) $b->description, $percent);

        $points = (int) round(self::WEIGHT_DESCRIPTION * ($percent / 100));

        if ($points > 0) {
            $reasons[] = sprintf('Description %d%% similar', (int) round($percent));
        }

        return $points;
    }

    /**
     * Determine whether a report has usable coordinates.
     */
    protected function hasCoordinates(Report $report): bool
    {
        return $report->latitude !== null && $report->longitude !== null;
    }

    /**
     * Great-circle distance between two points using the Haversine formula.
     */
    protected function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lon2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(
            sin($latDelta / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($lonDelta / 2) ** 2
        ));

        return $angle * self::EARTH_RADIUS_KM;
    }
}