# Smart Matching Engine & Async Processing

This document describes the automated lost<->found matching engine and the
asynchronous job that runs it.

## Overview

```
Report created (controller, DB transaction commits)
  -> GenerateMatches::dispatch($report->id)->afterCommit()
       MatchingService::score(report, candidate)  // 0-100
       keep candidates with score >= 40
       upsert into matches (unique [lost_report_id, found_report_id])
       notify counterpart owner for brand-new matches
```

## MatchingService (app/Services/MatchingService.php)

`score(Report $a, Report $b): array{score:int, reasons:array}` returns an
integer score (0-100, capped) plus human-readable `reasons`.

### Strict gates (score = 0 if any fail)

- Same `community_id`
- Same `category_id`
- Opposite, valid `type` (one `lost`, one `found`)

### Weighted criteria (sum = 100)

| Criterion                | Weight | Logic                                                        |
| ------------------------ | ----- | ------------------------------------------------------------ |
| Category                 | 30    | Guaranteed by the strict gate (same category).               |
| Geographic proximity     | 25    | Haversine distance; full points within 1 km, linearly decaying to 0 at 10 km. Skipped if coordinates missing. |
| Time proximity           | 20    | Based on `event_at`: <=6h -> 20, <=24h -> 15, <=72h -> 8, else 0. |
| Color                    | 10    | Case-insensitive equality of `color`.                        |
| Description similarity   | 15    | `similar_text()` percentage scaled to 15 points.             |

The minimum score to persist a match is `MatchingService::MIN_SCORE = 40`.

## GenerateMatches job (app/Jobs/GenerateMatches.php)

- `ShouldQueue` + `Queueable` (so `afterCommit()` works), `$tries = 3`.
- Constructor takes the report id (and an optional algorithm version).
- `handle(MatchingService $matcher)`:
  1. Loads the report; **no-ops if its status is not `open`**.
  2. Queries candidates: opposite type, same community **and category**,
     status `open` (never resolved/rejected/expired), excluding itself.
  3. Scores each candidate; skips those below `MIN_SCORE`.
  4. **Normalizes orientation** so `lost_report_id`/`found_report_id` are
     stored consistently no matter which report triggered the job - this keeps
     the unique constraint effective.
  5. Upserts via `updateOrCreate` keyed on `[lost_report_id, found_report_id]`
     -> idempotent on retries. A match a human already accepted/dismissed is
     never reset; only its score/reasons refresh.
  6. Dispatches a `MatchFound` (database) notification to the counterpart
     report owner for each **newly created** match.

## MatchFound notification (app/Notifications/MatchFound.php)

Database-channel notification carrying the match id, score, reasons, and the
counterpart report title.

## Controller integration

`LostFoundReportController@store` already dispatches the job immediately after
the report transaction:

```php
GenerateMatches::dispatch($report->id, 'v1.0')->afterCommit();
```

`afterCommit()` guarantees the worker only runs once the transaction is durably
committed, so it never matches against a half-written report.

## Testing

- `tests/Unit/MatchingServiceTest.php` - strict gates, perfect match (100),
  category-only floor (< threshold), time tiers, geographic decay, color
  case-insensitivity.
- `tests/Feature/GenerateMatchesTest.php` - creates match + notifies
  counterparty, below-threshold ignored, non-open report ignored, other
  community/category ignored, idempotent on retry (single match, single
  notification), orientation normalized when triggered by the found report.

Run:

```bash
php artisan test
```