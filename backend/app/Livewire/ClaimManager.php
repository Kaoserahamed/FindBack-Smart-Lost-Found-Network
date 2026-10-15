<?php

namespace App\Livewire;

use App\Models\Claim;
use App\Models\CommunityMembership;
use App\Models\Report;
use App\Traits\RecordsItemEvents;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layout')]
class ClaimManager extends Component
{
    use RecordsItemEvents, WithFileUploads;

    public Report $report;

    // Submission state
    public array $answers = [];

    public $evidence;

    // Review state (per-claim)
    public array $reviewDecision = [];
    public array $reviewReason = [];

    public function mount(Report $report): void
    {
        $this->report = $report->load(['community', 'category']);
    }

    /**
     * The claims the current user is allowed to review on this report.
     */
    public function reviewableClaims()
    {
        $user = Auth::user();

        return Claim::query()
            ->where('report_id', $this->report->id)
            ->with('claimant')
            ->when($this->report->user_id !== $user?->id, function ($q) use ($user) {
                // If not the reporter, only moderators of this community may see them.
                $q->whereHas('report.community.memberships', fn ($m) => $m
                    ->where('user_id', $user?->id)
                    ->where('role', 'moderator')
                    ->where('status', 'active'));
            })
            ->where('claimant_id', '!=', $user?->id) // never your own claim
            ->latest()
            ->get();
    }

    /**
     * Whether the current user may submit a claim on this found report.
     */
    public function canSubmit(): bool
    {
        $user = Auth::user();

        if (! $user || $this->report->type !== 'found' || $this->report->status !== 'open') {
            return false;
        }

        // Cannot claim your own found report.
        if ($this->report->user_id === $user->id) {
            return false;
        }

        return CommunityMembership::query()
            ->where('community_id', $this->report->community_id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
    }

    public function submitClaim(): void
    {
        $user = Auth::user();

        // Server-side authorization gate.
        abort_unless($this->canSubmit(), 403);

        $this->validate([
            'answers' => ['required', 'array', 'min:1'],
            'answers.*' => ['required', 'string', 'max:500'],
            'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ], [
            'answers.required' => 'Please provide at least one verification answer.',
            'evidence.mimes' => 'Evidence must be jpg, jpeg, png, or pdf.',
        ]);

        DB::transaction(function () use ($user) {
            $evidencePath = $this->evidence
                ? $this->evidence->store("claims/{$this->report->uuid}", 'local')
                : null;

            $claim = $this->report->claims()->create([
                'claimant_id' => $user->id,
                'answers' => $this->answers,
                'evidence_path' => $evidencePath,
                'status' => 'submitted',
            ]);

            $this->recordItemEvent($this->report, 'claim_submitted', $user->id, [
                'claim_id' => $claim->id,
            ]);
        });

        $this->reset(['answers', 'evidence']);
        session()->flash('success', 'Your claim has been submitted and is awaiting review.');
    }

    public function review(int $claimId, string $decision): void
    {
        $user = Auth::user();
        $claim = Claim::with('report')->findOrFail($claimId);

        // Authorization via the ClaimPolicy (reporter or active moderator, never claimant).
        abort_unless($user && $user->can('review', $claim), 403);

        abort_if(! in_array($decision, ['approved', 'rejected'], true), 422);

        $reason = trim($this->reviewReason[$claimId] ?? '');
        if ($reason === '') {
            $this->addError("reviewReason.$claimId", 'A decision reason is required.');
            return;
        }

        DB::transaction(function () use ($claim, $decision, $reason, $user) {
            $claim->update([
                'status' => $decision,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'decision_reason' => $reason,
            ]);

            if ($report = $claim->report) {
                $this->recordItemEvent($report, 'claim_reviewed', $user->id, [
                    'claim_id' => $claim->id,
                    'decision' => $decision,
                ]);
            }
        });

        $this->reset("reviewReason.$claimId");
        session()->flash('success', 'Claim '.$decision.'.');
    }

    public function render()
    {
        return view('livewire.claim-manager', [
            'claims' => $this->reviewableClaims(),
            'canSubmit' => $this->canSubmit(),
        ]);
    }
}
