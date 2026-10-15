# Claims & Handover Workflow

This document describes how ownership claims are submitted, reviewed, and
finalized (handover) for found reports.

## Flow overview

```
1. Submit   POST   /reports/{report}/claims        (StoreClaimRequest + ManageClaimAction)
2. Review   PATCH  /claims/{claim}/review          (ClaimPolicy::review + ManageClaimAction)
3. Handover POST   /claims/{claim}/complete-handover (transaction + row locks)
```

All three steps delegate to `App\Actions\ManageClaimAction`, the single source
of truth for the claim lifecycle. The HTTP controllers (`ClaimController`) and
the Livewire components (`ClaimManager`) both call into it, so the two entry
points can never drift apart.

States: `submitted` -> (`under_review` | `approved` | `rejected`) -> `completed`.
`rejected` and `completed` are **terminal** - see the transition table in
`App\Models\Claim::TRANSITIONS`.

## StoreClaimRequest (app/Http/Requests/StoreClaimRequest.php)

- `authorize()`: the user must be authenticated **and** an *active* member of
  the community that owns the target report. A reporter can never claim their
  own found item. (Previously this only checked authentication, which let any
  user claim any community's reports.)
- Validation:
  - `report_id`: required, integer, `exists:reports,id` **and** `type = found`
    **and** `status = open`, and must match the route-bound report.
  - `answers`: required array (min 1, max 10); each entry must be a string of
    at most 500 characters. These are the **private** verification answers.
  - `evidence_path`: nullable file, `mimes:jpg,jpeg,png,pdf`, max 5120 KB (5 MB).

## ClaimPolicy (app/Policies/ClaimPolicy.php)

`review(User $user, Claim $claim)` grants review rights when the user is:

- **not** the claimant (a claimant can never review their own claim), and
- either the **reporter of the found item**, or an **active `moderator`** of the
  report community.

Suspended moderators and unrelated users are denied. The policy is
auto-discovered by Laravel for the `Claim` model.

## ReportPolicy (app/Policies/ReportPolicy.php)

`view(User $user, Report $report)` gates the report detail pages
(`reports.show`, `reports.matches`) and the private photo stream: the user must
be the report's author or an active member of its community.

## ManageClaimAction (app/Actions/ManageClaimAction.php)

### submit(User $claimant, Report $report, array $answers, ?string $evidencePath)

- Re-checks every precondition *inside* the transaction on a locked report row:
  `type = found`, `status = open`, not the reporter's own item, claimant is an
  active community member, and no other live claim by this claimant exists.
- Creates the claim with `status = submitted`, persists the evidence path, and
  records a `claim_submitted` audit entry.
- Violation of any precondition raises a `ValidationException` (422).

### review(User $reviewer, Claim $claim, string $decision, string $reason)

- Locks the claim row, then validates the requested status against the legal
  transition table (`Claim::canTransitionTo()`). A decided claim (`rejected`,
  `completed`, or already-`approved`) can never be re-opened.
- Approving additionally requires the report to still be `open` and that no
  other approved claim exists for the same report ("one winner per report").
- Updates the claim, records a `claim_reviewed` audit event, then notifies the
  claimant (`ClaimStatusUpdated`) *after* the transaction commits.

### completeHandover(Request $request, Claim $claim)

- Locks **both** the claim row (guards against two concurrent handovers) and
  the report row (guards against two different approved claims handing the same
  item to two different people).
- Requires an `approved` claim on a still-`open` report (otherwise `422`).
- Marks the claim `completed`, resolves the report, and logs a
  `custody_transferred` audit event with the recipient user id.

## Authorization note

`$this->authorize()` is available because the base `App\Http\Controllers\Controller`
uses the `AuthorizesRequests` trait.

## Claim data model

- `answers` are persisted **encrypted at rest** (`encrypted:array` cast on a
  `text` column). Run `php artisan claims:encrypt-answers` once after deploy to
  backfill rows written before encryption existed.
- `evidence_path` holds files on the **private** disk (`storage/app/private`);
  the disk is intentionally *not* web-served. Photos are streamed through
  `PhotoController`, which applies the `ReportPolicy` first.
- Database-level backstops: `claims_one_active_per_claimant` (one live claim
  per claimant per report), `claims_one_winner_per_report` (a single item can
  never be awarded twice), plus a composite reports index for the matcher.

## Testing

`tests/Feature/ClaimWorkflowTest.php` covers:

- authenticated user submits a claim on a **found** report (private evidence
  stored, audit event recorded)
- guest redirected to `/login`
- cannot claim a **lost** report (`report_id` must be `found`)
- claim requires `answers`
- found reporter can review (approve/reject) with a reason
- active moderator can review; **suspended** moderator cannot
- claimant cannot review their own claim; unrelated user cannot review
- completing handover marks claim `completed` + report `resolved` +
  `custody_transferred` event
- handover blocked unless the claim is `approved` (422)
- claimant cannot complete a handover

Run:

```bash
php artisan test --filter=ClaimWorkflowTest
```## Security regression suites

- 	ests/Feature/SecurityRegressionTest.php reproduces every attack from the
  security audit and proves it now fails (403/422 + 0 bad rows).
- 	ests/Feature/EncryptionTest.php proves answers are ciphertext at rest,
  still readable through the model, and that the backfill command is exact and
  idempotent.
- 	ests/Feature/RateLimitingTest.php proves the login (5/min) and claim
  submission (10/min) endpoints return 429 under abuse.
- 	ests/Feature/AuditFixesTest.php proves the notification references the
  correct report, expired reports are never matched, and the N+1 query
  regressions are gone.