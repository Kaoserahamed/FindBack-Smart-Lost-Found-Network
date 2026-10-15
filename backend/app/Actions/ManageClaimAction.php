<?php

namespace App\Actions;

use App\Models\Claim;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ClaimStatusUpdated;
use App\Traits\RecordsItemEvents;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Single source of truth for the claim lifecycle.
 *
 * Both the HTTP controllers and the Livewire components delegate here so the
 * two entry points can never drift apart (previously the Livewire path enforced
 * community membership, report state and answer limits while the HTTP path
 * enforced none of them).
 */
class ManageClaimAction
{
    use RecordsItemEvents;

    /**
     * Submit a claim against a found report.
     *
     * Every precondition is re-checked inside the transaction so a concurrent
     * status change cannot slip a claim onto a report that has just been
     * resolved.
     *
     * @param  array<int|string, mixed>  $answers
     *
     * @throws ValidationException
     */
    public function submit(User $claimant, Report $report, array $answers, ?string $evidencePath = null): Claim
    {
        return DB::transaction(function () use ($claimant, $report, $answers, $evidencePath) {
            // Lock the report so its status/type cannot change underneath us.
            $report = Report::whereKey($report->getKey())->lockForUpdate()->firstOrFail();

            throw_if(
                $report->type !== 'found',
                ValidationException::withMessages(['report_id' => 'Only found items can be claimed.'])
            );

            throw_if(
                $report->status !== 'open',
                ValidationException::withMessages(['report_id' => 'This report is no longer open for claims.'])
            );

            // A reporter may never claim their own found item.
            throw_if(
                $report->user_id === $claimant->id,
                ValidationException::withMessages(['report_id' => 'You cannot claim your own report.'])
            );

            // Community isolation: the claimant must be an active member of the
            // community the report belongs to.
            throw_unless(
                $report->community->memberships()
                    ->where('user_id', $claimant->id)
                    ->where('status', 'active')
                    ->exists(),
                ValidationException::withMessages(['report_id' => 'You are not a member of this community.'])
            );

            // One live claim per claimant per report.
            throw_if(
                $report->claims()
                    ->where('claimant_id', $claimant->id)
                    ->whereIn('status', Claim::ACTIVE_STATUSES)
                    ->exists(),
                ValidationException::withMessages(['report_id' => 'You already have an active claim on this report.'])
            );

            $claim = $report->claims()->create([
                'claimant_id' => $claimant->id,
                'answers' => $answers,
                'evidence_path' => $evidencePath,
                'status' => Claim::STATUS_SUBMITTED,
            ]);

            $this->recordItemEvent($report, 'claim_submitted', $claimant->id, [
                'claim_id' => $claim->id,
            ]);

            return $claim;
        });
    }

    /**
     * Record a reviewer's decision (approve / reject) on a claim.
     *
     * The claim row is locked for the duration of the transaction and the
     * requested status is validated against the legal transition table, so a
     * claim that has already been decided can never be re-opened.
     *
     * @throws HttpExceptionInterface
     */
    public function review(User $reviewer, Claim $claim, string $decision, string $reason): Claim
    {
        $updated = DB::transaction(function () use ($reviewer, $claim, $decision, $reason) {
            $claim = Claim::whereKey($claim->getKey())->lockForUpdate()->firstOrFail();

            abort_if(
                ! $claim->canTransitionTo($decision),
                422,
                'This claim has already been decided and cannot be reviewed again.'
            );

            // Guard the "one winner per report" invariant before writing. The
            // partial unique index is the hard backstop, but without this check
            // a second approval would surface as a 500 instead of a clean 422.
            if ($decision === Claim::STATUS_APPROVED) {
                $report = Report::whereKey($claim->report_id)->lockForUpdate()->firstOrFail();

                abort_if(
                    $report->status !== 'open',
                    422,
                    'This report has already been resolved, so no further claim can be approved.'
                );

                abort_if(
                    $report->claims()
                        ->whereKeyNot($claim->getKey())
                        ->where('status', Claim::STATUS_APPROVED)
                        ->exists(),
                    422,
                    'Another claim on this report has already been approved.'
                );
            }

            $claim->update([
                'status' => $decision,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'decision_reason' => $reason,
            ]);

            if ($report = $claim->report) {
                $this->recordItemEvent($report, 'claim_reviewed', $reviewer->id, [
                    'claim_id' => $claim->id,
                    'decision' => $decision,
                ]);
            }

            return $claim;
        });

        // Notify only after the decision is durably committed.
        $updated->claimant?->notify(new ClaimStatusUpdated($updated));

        return $updated;
    }

    /**
     * Finalise custody transfer for an approved claim.
     *
     * Locks the claim row (guarding against two concurrent handovers) and the
     * report row (guarding against two different approved claims both handing
     * the same physical item to two different people).
     *
     * @throws HttpExceptionInterface
     */
    public function completeHandover(User $actor, Claim $claim): Claim
    {
        return DB::transaction(function () use ($actor, $claim) {
            $claim = Claim::whereKey($claim->getKey())->lockForUpdate()->firstOrFail();

            abort_if(
                $claim->status !== Claim::STATUS_APPROVED,
                422,
                'Only an approved claim can be completed.'
            );

            $report = Report::whereKey($claim->report_id)->lockForUpdate()->firstOrFail();

            // The report must not already have been resolved by another claim.
            abort_if(
                $report->status !== 'open',
                422,
                'This report has already been resolved.'
            );

            $claim->update(['status' => Claim::STATUS_COMPLETED]);

            $report->update(['status' => 'resolved']);

            $this->recordItemEvent($report, 'custody_transferred', $actor->id, [
                'claim_id' => $claim->id,
                'to_user_id' => $claim->claimant_id,
            ]);

            return $claim;
        });
    }
}
