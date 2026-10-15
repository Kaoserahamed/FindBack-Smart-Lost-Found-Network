<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReportRequest;
use App\Jobs\GenerateMatches;
use App\Models\Report;
use App\Traits\RecordsItemEvents;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class LostFoundReportController extends Controller
{
    use RecordsItemEvents;

    /**
     * Store a newly created lost/found report.
     *
     * The entire operation runs inside a database transaction so that the
     * report, its photos, and its audit-trail entry are created atomically.
     * Only after the transaction commits do we dispatch the asynchronous
     * matching job, guaranteeing the worker never sees a half-written report.
     */
    public function store(StoreReportRequest $request): RedirectResponse
    {
        $report = DB::transaction(function () use ($request) {
            /** @var \App\Models\User $user */
            $user = $request->user();

            // 1. Create the report. The UUID is generated automatically by the
            //    Report model's "creating" hook, so we never expose it to input.
            $report = Report::create([
                ...$request->safe()->except(['photos']),
                'user_id' => $user->id,
            ]);

            // 2. Persist any uploaded photos to private storage and link them.
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $path = $photo->store("reports/{$report->uuid}", 'local');

                    $report->photos()->create([
                        'storage_path' => $path,
                        'visibility' => 'private',
                    ]);
                }
            }

            // 3. Record the initial audit-trail event.
            $this->recordItemEvent($report, 'created', $user->id, [
                'type' => $report->type,
                'community_id' => $report->community_id,
                'photos' => $report->photos()->count(),
            ]);

            return $report;
        });

        // 4. Queue match generation only after the transaction has committed.
        GenerateMatches::dispatch($report->id, 'v1.0')->afterCommit();

        return back()->with('success', 'Your report has been submitted successfully.');
    }
}
