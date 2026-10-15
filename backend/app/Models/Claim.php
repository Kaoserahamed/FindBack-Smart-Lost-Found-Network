<?php

namespace App\Models;

use Database\Factories\ClaimFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'report_id',
    'claimant_id',
    'answers',
    'evidence_path',
    'status',
    'reviewed_by',
    'reviewed_at',
    'decision_reason',
])]
class Claim extends Model
{
    /** @use HasFactory<ClaimFactory> */
    use HasFactory;

    /** Claim has been submitted and is awaiting a reviewer decision. */
    public const STATUS_SUBMITTED = 'submitted';

    /** A reviewer has picked the claim up but not yet decided. */
    public const STATUS_UNDER_REVIEW = 'under_review';

    /** A reviewer verified ownership; the item may now be handed over. */
    public const STATUS_APPROVED = 'approved';

    /** A reviewer rejected the claim. Terminal - the claimant cannot retry. */
    public const STATUS_REJECTED = 'rejected';

    /** Custody has been transferred. Terminal. */
    public const STATUS_COMPLETED = 'completed';

    /**
     * The statuses a claim may still be *actively* claimed in. Used to stop a
     * single claimant from stacking duplicate claims on one report.
     *
     * @var array<int, string>
     */
    public const ACTIVE_STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_APPROVED,
    ];

    /**
     * Legal status transitions.
     *
     * This is the single source of truth for the claim lifecycle. Enforcing it
     * here (rather than per-controller) means a decided claim can never be
     * silently re-opened - e.g. a `rejected` or `completed` claim cannot be
     * flipped back to `approved`, which previously allowed the same item to be
     * handed over more than once.
     *
     * @var array<string, array<int, string>>
     */
    public const TRANSITIONS = [
        self::STATUS_SUBMITTED => [
            self::STATUS_UNDER_REVIEW,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
        ],
        self::STATUS_UNDER_REVIEW => [
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
        ],
        self::STATUS_APPROVED => [
            self::STATUS_COMPLETED,
        ],
        // Terminal states: no outgoing transitions.
        self::STATUS_REJECTED => [],
        self::STATUS_COMPLETED => [],
    ];

    /**
     * Whether this claim may legally move to the given status.
     */
    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * `answers` holds private ownership-verification data (serial numbers,
     * unique marks). It is encrypted at rest so a database dump, backup or
     * read-replica does not disclose it in plaintext.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answers' => 'encrypted:array',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * The report being claimed.
     *
     * @return BelongsTo<Report, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * The user making the claim.
     *
     * @return BelongsTo<User, $this>
     */
    public function claimant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimant_id');
    }

    /**
     * The user (moderator/admin) who reviewed the claim.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
