<?php

namespace App\Jobs;

use App\Models\ItemMatch;
use App\Models\Report;
use App\Notifications\PotentialMatchFound;
use App\Services\MatchingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateMatches implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     *
     * @param  int  $reportId  The newly created report to find matches for.
     * @param  string  $algorithmVersion  Version of the matching algorithm to run.
     */
    public function __construct(
        public int $reportId,
        public string $algorithmVersion = MatchingService::ALGORITHM_VERSION,
    ) {}

    /**
     * Execute the job.
     *
     * Compares the given report against candidate reports of the opposite type
     * within the same community and category, scores each pairing with the
     * MatchingService, and upserts any candidate that clears the minimum score
     * threshold into the matches table. Newly created matches trigger a
     * notification to the owner of the counterpart report.
     */
    public function handle(MatchingService $matcher): void
    {
        $report = Report::find($this->reportId);

        if (! $report) {
            Log::warning('GenerateMatches: report not found.', ['report_id' => $this->reportId]);

            return;
        }

        // Only match reports that are still open.
        if ($report->status !== 'open') {
            return;
        }

        $oppositeType = $report->type === 'lost' ? 'found' : 'lost';

        // Candidate reports: opposite type, same community & category, still open
        // (never resolved/rejected/expired), excluding the report itself.
        $candidates = Report::query()
            ->where('community_id', $report->community_id)
            ->where('category_id', $report->category_id)
            ->where('type', $oppositeType)
            ->where('status', 'open')
            ->whereKeyNot($report->id)
            ->get();

        $newMatches = 0;

        foreach ($candidates as $candidate) {
            $result = $matcher->score($report, $candidate);

            if ($result['score'] < MatchingService::MIN_SCORE) {
                continue;
            }

            // Normalize orientation so the unique [lost_report_id, found_report_id]
            // constraint is stable regardless of which report triggered the job.
            $lostId = $report->type === 'lost' ? $report->id : $candidate->id;
            $foundId = $report->type === 'found' ? $report->id : $candidate->id;

            $existing = ItemMatch::where('lost_report_id', $lostId)
                ->where('found_report_id', $foundId)
                ->first();

            // Do not clobber a match a human has already accepted or dismissed;
            // only refresh its score/reasons so retries stay idempotent.
            if ($existing && $existing->status !== 'pending') {
                $existing->update([
                    'score' => $result['score'],
                    'reasons' => $result['reasons'],
                    'algorithm_version' => $this->algorithmVersion,
                ]);

                continue;
            }

            $match = ItemMatch::updateOrCreate(
                ['lost_report_id' => $lostId, 'found_report_id' => $foundId],
                [
                    'score' => $result['score'],
                    'reasons' => $result['reasons'],
                    'status' => 'pending',
                    'algorithm_version' => $this->algorithmVersion,
                ],
            );

            // Notify the counterpart report's owner about a brand-new match.
            if ($match->wasRecentlyCreated && $candidate->user_id) {
                $candidate->user?->notify(new PotentialMatchFound($match));
                $newMatches++;
            }
        }

        if ($newMatches > 0) {
            Log::info('GenerateMatches: created new matches.', [
                'report_id' => $report->id,
                'new_matches' => $newMatches,
                'algorithm_version' => $this->algorithmVersion,
            ]);
        }
    }
}

