# Notifications & Audit Trail

This document describes user notifications and the reusable audit-trail helper.

## Notifications (app/Notifications)

Both notifications deliver on **two channels**: `database` (dashboard) and
`mail`, and implement `ShouldQueue` so sending never blocks the request.

### PotentialMatchFound

- Triggered by the `GenerateMatches` job when a **new** match (score >= 40) is
  persisted.
- Sent to the owner of the counterpart report.
- `toArray()` payload: `match_id`, `score`, `reasons`, `report_uuid`,
  `report_title`, `summary`.
- `toMail()` renders a friendly email with the match score and related report.

> Replaces the earlier `MatchFound` notification (same purpose, now with the
> `mail` channel).

### ClaimStatusUpdated

- Triggered by `ClaimController@review` when a claim is **approved** or
  **rejected**.
- Sent to the **claimant**.
- `toArray()` payload: `claim_id`, `status`, `decision_reason`, `report_uuid`,
  `report_title`, `summary`.
- `toMail()` renders the new status and (if present) the decision reason.

## Audit trail helper (app/Traits/RecordsItemEvents.php)

A small trait providing a single, reusable method:

```php
$this->recordItemEvent(Report $report, string $action, ?int $userId = null, array $metadata = []);
```

- Writes to the `item_events` table (linked to the report).
- `metadata` is stored as JSON; an empty array is persisted as `NULL`.
- Used by both controllers so every workflow logs consistently.

### Integration points

| Workflow                         | Action                 | Logged in                         |
| -------------------------------- | ---------------------- | --------------------------------- |
| Report creation                  | `created`              | LostFoundReportController@store   |
| Claim submission                 | `claim_submitted`      | ClaimController@store             |
| Claim review (approve/reject)    | `claim_reviewed`       | ClaimController@review            |
| Handover completion              | `custody_transferred`  | ClaimController@completeHandover  |

## Notification dispatching

- `GenerateMatches` job: after `updateOrCreate` persists a **new** match
  (`wasRecentlyCreated`), it calls
  `$candidate->user?->notify(new PotentialMatchFound($match))`. Idempotent on
  retries (no re-notify for pre-existing matches).
- `ClaimController@review`: after the status is updated (inside/after the
  transaction), it calls `$claim->claimant?->notify(new ClaimStatusUpdated($claim))`.

## Testing

`tests/Feature/NotificationTest.php` covers:

- `PotentialMatchFound` exposes `database` + `mail`, and `toArray`/`toMail`
  build correctly.
- `ClaimStatusUpdated` exposes `database` + `mail`, and `toArray`/`toMail`
  build correctly.
- Reviewing a claim notifies the claimant (approve and reject).

Existing suites (`GenerateMatchesTest`, `ClaimWorkflowTest`, `StoreReportTest`)
continue to assert the audit events produced via the trait.

Run:

```bash
php artisan test
```