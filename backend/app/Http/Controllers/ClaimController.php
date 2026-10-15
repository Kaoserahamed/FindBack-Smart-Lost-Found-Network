<?php

namespace App\Http\Controllers;

use App\Actions\ManageClaimAction;
use App\Http\Requests\StoreClaimRequest;
use App\Models\Claim;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClaimController extends Controller
{
    public function __construct(
        private readonly ManageClaimAction $claims,
    ) {}

    /**
     * Store a newly submitted claim against a found report.
     *
     * All business rules (community isolation, report state, self-claim,
     * duplicate claims) live in ManageClaimAction so this controller and the
     * Livewire component behave identically.
     */
    public function store(StoreClaimRequest $request, Report $report): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $evidencePath = null;
        if ($request->hasFile('evidence_path')) {
            $evidencePath = $request->file('evidence_path')->store("claims/{$report->uuid}", 'local');
        }

        $this->claims->submit($user, $report, $request->input('answers'), $evidencePath);

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

        /** @var User $user */
        $user = $request->user();

        $this->claims->review(
            $user,
            $claim,
            $validated['decision'],
            $validated['decision_reason'],
        );

        $message = $validated['decision'] === 'approved'
            ? 'Claim approved.'
            : 'Claim rejected.';

        return back()->with('success', $message);
    }

    /**
     * Finalize the handover for an approved claim.
     *
     * Delegates to ManageClaimAction, which runs inside a transaction with row
     * locks on both the claim and its report to prevent race conditions (e.g.
     * two concurrent handovers, or two approved claims for one item).
     */
    public function completeHandover(Request $request, Claim $claim): RedirectResponse
    {
        $this->authorize('review', $claim);

        /** @var User $user */
        $user = $request->user();

        $this->claims->completeHandover($user, $claim);

        return back()->with('success', 'Handover completed. The item has been returned.');
    }
}
