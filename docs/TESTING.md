# Testing

FindBack uses **PHPUnit** via `php artisan test` (Pest is not installed, so the
suite follows the existing PHPUnit `TestCase` conventions). Tests run against a
dedicated PostgreSQL database (`findback_testing`) to exercise the real schema
(jsonb, uuid, enum CHECK constraints). See `phpunit.xml`.

> The `findback_testing` database must exist and the PostgreSQL service must be
> running before tests execute:
>
> ```sql
> CREATE DATABASE findback_testing;
> ```
>
> ```powershell
> Start-Service postgresql-x64-15
> ```

## Running

```bash
php artisan test                       # full suite
php artisan test --filter=ClaimWorkflowTest   # a single class
```

## Coverage

### tests/Feature/StoreReportTest.php
Report submission: happy path (photos, audit event, `GenerateMatches`
dispatched), guest redirect, non-member/suspended forbidden, validation
failures, photo limits and mime types.

### tests/Feature/GenerateMatchesTest.php
Matching engine job: creates a match + notifies counterparty, below-threshold
ignored, non-open report ignored, other community/category ignored, idempotent
on retry, orientation normalized.

### tests/Feature/ClaimWorkflowTest.php
Claims & handover: submission on a found report, guest redirect, lost-report
rejection, answers required, reporter/moderator review, claimant/outsider/
suspended-moderator denied, handover completion, approved-only guard, claimant
cannot complete.

### tests/Feature/NotificationTest.php
`PotentialMatchFound` and `ClaimStatusUpdated` expose `database` + `mail` with
correct payloads; claimant notified on approve and reject.

### tests/Unit/MatchingServiceTest.php
Scoring algorithm: strict gates, perfect match = 100, category-only floor, time
tiers, geographic decay, color case-insensitivity.

### tests/Feature/CommunityIsolationTest.php
Community isolation & authorization:
- guest cannot create a report or submit a claim (redirect to `/login`)
- non-member / suspended member cannot create a report in a community (403)
- claimant cannot review their own claim (403)
- a moderator of a *different* community cannot review a claim (403)
- an unrelated user cannot review another user claim (403)

### tests/Feature/MatchingEngineTest.php
- valid lost/found reports in the same community + category generate a match
  (>= MIN_SCORE, orientation lost->found)
- cross-community reports do **not** generate a match
- running the job multiple times does **not** create duplicate matches (unique
  `[lost_report_id, found_report_id]`)

### tests/Feature/HandoverConcurrencyTest.php
- claim store records private answers and stores evidence on the private disk
  (never on the public disk)
- handover marks the claim `completed`, the report `resolved`, and logs a single
  `custody_transferred` event
- handover is rejected unless the claim is `approved` (422)
- handover issues a `SELECT ... FOR UPDATE` row lock (asserted via query log)
- concurrent/racing handover attempts complete only once (no duplicate events)

## Notes

- `jsonb` columns do not guarantee key order, so assertions compare stored
  values individually rather than whole-array equality.
- Row-locking is verified two ways: the query log proves `FOR UPDATE` is issued,
  and the serialized double-handover test proves the practical guarantee (only
  one completion, one audit event). True cross-connection lock contention is not
  asserted because `RefreshDatabase` wraps each test in a single transaction.