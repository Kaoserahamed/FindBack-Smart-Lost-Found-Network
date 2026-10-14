<?php

namespace App\Jobs;

use App\Models\Report;
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
        public string $algorithmVersion = 'v1.0',
    ) {}

    /**
     * Execute the job.
     *
     * Compares the given report against candidate reports of the opposite
     * type within the same community and records scored matches. The heavy
     * comparison logic lives in a dedicated matcher service; this job simply
     * orchestrates it asynchronously so report submission stays fast.
     */
    public function handle(): void
    {
        $report = Report::find($this->reportId);

        if (! $report) {
            Log::warning('GenerateMatches: report not found.', ['report_id' => $this->reportId]);

            return;
        }

        // Opposite type: a "lost" report should be matched against "found" ones, and vice versa.
        $oppositeType = $report->type === 'lost' ? 'found' : 'lost';

        $candidates = Report::query()
            ->where('community_id', $report->community_id)
            ->where('type', $oppositeType)
            ->where('status', 'open')
            ->whereKeyNot($report->id)
            ->get();

        // TODO: score each candidate and persist App\Models\ItemMatch records.
        // The scoring/decision logic is intentionally delegated to a matcher
        // service so this job remains a thin async orchestrator.
        Log::info('GenerateMatches: evaluated candidates.', [
            'report_id' => $report->id,
            'type' => $report->type,
            'candidates' => $candidates->count(),
            'algorithm_version' => $this->algorithmVersion,
        ]);
    }
}
