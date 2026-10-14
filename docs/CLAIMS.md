# Claims & Handover Workflow

This document describes how ownership claims are submitted, reviewed, and
finalized (handover) for found reports.

## Flow overview

```
1. Submit   POST   /reports/{report}/claims        (StoreClaimRequest)
2. Review   PATCH  /claims/{claim}/review          (ClaimPolicy::review)
3. Handover POST   /claims/{claim}/complete-handover (transaction + lockForUpdate)
```

States: `submitted` -> `under_review`/`approved`/`rejected` -> `completed`.

## StoreClaimRequest (app/Http/Requests/StoreClaimRequest.php)

- `authorize()`: the user must be authenticated.
- Validation:
  - `report_id`: required, integer, `exists:reports,id` **and** `type = found`
    (enforced with `Rule::exists(...)->where('type','found')`), and must match
    the route-bound report.
  - `answers`: required array (min 1); each entry required. These are the
    **private** verification answers.
  - `evidence_path`: nullable file, `mimes:jpg,jpeg,png,pdf`, max 5120 KB (5 MB).

## ClaimPolicy (app/Policies/ClaimPolicy.php)

`review(User $user, Claim $claim)` grants review rights when the user is:

- **not** the claimant (a claimant can never review their own claim), and
- either the **reporter of the found item**, or an **active `moderator`** of the
  report community.

Suspended moderators and unrelated users are denied. The policy is
auto-discovered by Laravel for the `Claim` model.

## ClaimController (app/Http/Controllers/ClaimController.php)

### store(StoreClaimRequest $request, Report $report)

- Runs in a `DB::transaction`.
- Creates the claim with `status = submitted`, the claimant id, and the JSON
  `answers` (persisted to the `jsonb` column, cast to array on read).
- If evidence was uploaded, it is stored in **private** storage
  (`storage/app/private`) and only its path is saved to `evidence_path`.
- Records an `item_events` audit entry (`action = claim_submitted`).
- Redirects back with a success message.

### review(Request $request, Claim $claim)

- Authorized via `$this->authorize('review', $claim)`.
- Validates `decision` (`approved`|`rejected`) and `decision_reason` (required).
- Updates the claim status, `reviewed_by`, `reviewed_at`, and `decision_reason`.
- Records a `claim_reviewed` audit event.

### completeHandover(Request $request, Claim $claim)

- Authorized via the same `review` ability.
- Wrapped in `DB::transaction` with `lockForUpdate()` on the claim row to
  **prevent race conditions** (e.g. two concurrent handover requests).
- State guard: only an `approved` claim can be completed (otherwise `422`).
- Marks the claim `completed`, sets the related report to `resolved`, and logs
  a final `custody_transferred` audit event with the recipient user id.

## Authorization note

`$this->authorize()` is available because the base `App\Http\Controllers\Controller`
uses the `AuthorizesRequests` trait.

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
```