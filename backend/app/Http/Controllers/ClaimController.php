<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClaimRequest;
use App\Models\Claim;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClaimController extends Controller
{
    /**
     * Store a newly submitted claim against a found report.
     *
     * The claim, its private verification answers, and the audit-trail entry
     * are written atomically. Any uploaded evidence is stored in private
     * storage and only its path is persisted.
     */
    public function store(StoreClaimRequest $request, Report $report): RedirectResponse
    {
        $claim = DB::transaction(function () use ($request, $report) {
            /** @var \App\Models\User $user */
            $user = $request->user();

            $evidencePath = null;
            if ($request->hasFile('evidence_path')) {
                $evidencePath = $request->file('evidence_path')->store("claims/{$report->uuid}", 'local');
            }

            $claim = $report->claims()->create([
                'claimant_id' => $user->id,
                'answers' => $request->input('answers'),
                'evidence_path' => $evidencePath,
                'status' => 'submitted',
            ]);

            $report->events()->create([
                'user_id' => $user->id,
                'action' => 'claim_submitted',
                'metadata' => [
                    'claim_id' => $claim->id,
                ],
            ]);

            return $claim;
        });

        return back()->with('success', 'Your claim has been submitted and is awaiting review.');
    }

    /**
     * Approve or reject a claim.
     *
     * Authorization is enforced by the ClaimPolicy (found-report reporter or an
     * active community moderator, never the claimant).
     */
    public function review(Request $request, Claim $claim): RedirectResponse
    {
        $this->authorize('review', $claim);

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'decision_reason' => ['required', 'string', 'max:1000'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        DB::transaction(function () use ($claim, $validated, $user) {
            $claim->update([
                'status' => $validated['decision'],
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'decision_reason' => $validated['decision_reason'],
            ]);

            $claim->report?->events()->create([
                'user_id' => $user->id,
                'action' => 'claim_reviewed',
                'metadata' => [
                    'claim_id' => $claim->id,
                    'decision' => $validated['decision'],
                ],
            ]);
        });

        $message = $validated['decision'] === 'approved'
            ? 'Claim approved.'
            : 'Claim rejected.';

        return back()->with('success', $message);
    }

    /**
     * Finalize the handover for an approved claim.
     *
     * Runs inside a transaction with a row lock on the claim to prevent race
     * conditions (e.g. two concurrent handovers). Marks the claim completed,
     * resolves the report, and records a custody-transfer audit event.
     */
    public function completeHandover(Request $request, Claim $claim): RedirectResponse
    {
        $this->authorize('review', $claim);

        DB::transaction(function () use ($claim, $request) {
            // Lock the claim row for the duration of the transaction.
            $claim = Claim::whereKey($claim->getKey())->lockForUpdate()->firstOrFail();

            // Idempotency / state guard: only an approved claim can be completed.
            if ($claim->status !== 'approved') {
                abort(422, 'Only an approved claim can be completed.');
            }

            /** @var \App\Models\User $user */
            $user = $request->user();

            $claim->update([
                'status' => 'completed',
            ]);

            $report = $claim->report;
            $report?->update([
                'status' => 'resolved',
            ]);

            $report?->events()->create([
                'user_id' => $user->id,
                'action' => 'custody_transferred',
                'metadata' => [
                    'claim_id' => $claim->id,
                    'to_user_id' => $claim->claimant_id,
                ],
            ]);
        });

        return back()->with('success', 'Handover completed. The item has been returned.');
    }
}
