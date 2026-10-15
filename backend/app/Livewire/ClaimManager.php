<?php

namespace App\Livewire;

use App\Actions\ManageClaimAction;
use App\Models\Claim;
use App\Models\CommunityMembership;
use App\Models\Report;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layout')]
class ClaimManager extends Component
{
    use WithFileUploads;

    public Report $report;

    /**
     * Resolve the shared claim lifecycle action.
     *
     * Livewire instantiates components with `new` (so constructor injection is
     * unavailable) and serializes component state between requests, so the
     * action is deliberately never stored on a property.
     */
    private function claims(): ManageClaimAction
    {
        return app(ManageClaimAction::class);
    }

    // Submission state
    public array $answers = [];

    public $evidence;

    // Review state (per-claim)
    public array $reviewDecision = [];

    public array $reviewReason = [];

    public function mount(Report $report): void
    {
        $this->report = $report->load(['community', 'category']);

        // Object-level authorization: only the author or an active community
        // member may open the claim manager for this report. Uses abort_unless
        // (an HttpException) rather than $this->authorize() so the 403 renders
        // consistently on both full-page and Livewire AJAX requests.
        abort_unless(auth()->user()?->can('view', $this->report), 403);
    }

    /**
     * The claims the current user is allowed to review on this report.
     *
     * Eager-loads the claimant to avoid an N+1 across the review list.
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

        abort_unless($user, 403);

        $this->validate([
            'answers' => ['required', 'array', 'min:1', 'max:10'],
            'answers.*' => ['required', 'string', 'max:500'],
            'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ], [
            'answers.required' => 'Please provide at least one verification answer.',
            'evidence.mimes' => 'Evidence must be jpg, jpeg, png, or pdf.',
        ]);

        $evidencePath = $this->evidence
            ? $this->evidence->store("claims/{$this->report->uuid}", 'local')
            : null;

        // All business rules (community isolation, report state, self-claim,
        // duplicate claims) are enforced by ManageClaimAction.
        $this->claims()->submit($user, $this->report, $this->answers, $evidencePath);

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

        // State transitions, row locking and the audit event all live in the action.
        $this->claims()->review($user, $claim, $decision, $reason);

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
