# Database Schema - FindBack

This document describes the PostgreSQL schema for FindBack, as defined by the
Laravel migrations in `backend/database/migrations/`.

- **Engine:** PostgreSQL 15
- **Enum columns** are implemented as `VARCHAR(255)` with `CHECK` constraints.
- **JSON columns** use `jsonb` (indexable, efficient).
- **Foreign keys** specify explicit `ON DELETE` behaviour to protect data
  integrity (see each table below).

> **Naming note:** The `matches` table is backed by the Eloquent model
> `App\Models\ItemMatch`. The model is named `ItemMatch` because `match` is a
> reserved keyword in PHP 8; it maps to the `matches` table via `$table`.

---

## Entity-relationship overview

```
communities 1---* community_memberships *---1 users
communities 1---* reports
categories  1---* reports          (self-referencing parent/children)
users       1---* reports
reports     1---* report_photos
reports     1---* matches          (as lost_report_id)
reports     1---* matches          (as found_report_id)
reports     1---* claims
reports     1---* item_events
users       1---* claims           (as claimant_id)
users       1---* claims           (as reviewed_by)
users       1---* item_events      (nullable)
```

---

## Tables

### `communities`
Scoped spaces where items are lost and found.

| Column        | Type          | Notes                                              |
| ------------- | ------------- | -------------------------------------------------- |
| id            | bigint (PK)   |                                                    |
| name          | varchar       |                                                    |
| slug          | varchar       | **unique**                                         |
| type          | enum          | `university`, `transport`, `shopping_mall`, `workplace`, `residential`, `other` |
| description   | text          | nullable                                           |
| address       | varchar       | nullable                                           |
| latitude      | decimal(10,7) | nullable                                           |
| longitude     | decimal(10,7) | nullable                                           |
| status        | enum          | `pending`, `approved`, `rejected` - default `approved` |
| created_at / updated_at | timestamp |                                               |

**Indexes:** `slug` (unique), `type`, `status`

---

### `categories`
Item categories, supporting one level of nesting (parent/child).

| Column        | Type          | Notes                                              |
| ------------- | ------------- | -------------------------------------------------- |
| id            | bigint (PK)   |                                                    |
| name          | varchar       |                                                    |
| slug          | varchar       | **unique**                                         |
| parent_id     | bigint (FK)   | -> `categories.id`, nullable, `ON DELETE SET NULL` |
| is_active     | boolean       | default `true`                                     |
| created_at / updated_at | timestamp |                                               |

**Indexes:** `slug` (unique), `parent_id`, `is_active`

---

### `community_memberships`
Join table linking users to communities with a role.

| Column        | Type          | Notes                                              |
| ------------- | ------------- | -------------------------------------------------- |
| id            | bigint (PK)   |                                                    |
| community_id  | bigint (FK)   | -> `communities.id`, `ON DELETE CASCADE`           |
| user_id       | bigint (FK)   | -> `users.id`, `ON DELETE CASCADE`                 |
| role          | enum          | `member`, `moderator`, `admin` - default `member`  |
| status        | enum          | `active`, `suspended` - default `active`           |
| created_at / updated_at | timestamp |                                               |

**Constraints:** **unique** `(community_id, user_id)` - **Indexes:** `role`, `status`

---

### `reports`
Lost/found item reports - the central table of the domain.

| Column        | Type          | Notes                                              |
| ------------- | ------------- | -------------------------------------------------- |
| id            | bigint (PK)   |                                                    |
| uuid          | uuid / char(36) | **unique**; auto-generated on create             |
| user_id       | bigint (FK)   | -> `users.id`, `ON DELETE CASCADE`                 |
| community_id  | bigint (FK)   | -> `communities.id`, `ON DELETE CASCADE`           |
| category_id   | bigint (FK)   | -> `categories.id`, `ON DELETE RESTRICT`           |
| type          | enum          | `lost`, `found`                                    |
| title         | varchar       |                                                    |
| description   | text          |                                                    |
| color         | varchar       | nullable                                           |
| brand         | varchar       | nullable                                           |
| event_at      | timestamp     | when the item was lost/found                       |
| location_name | varchar       |                                                    |
| latitude      | decimal(10,7) | nullable                                           |
| longitude     | decimal(10,7) | nullable                                           |
| status        | enum          | `open`, `under_review`, `resolved`, `rejected`, `expired` - default `open` |
| expires_at    | timestamp     | nullable                                           |
| created_at / updated_at | timestamp |                                               |

**Indexes:** `uuid` (unique), **composite `(community_id, type, status)`**, `category_id`, `event_at`, `status`, `user_id`
---

### `report_photos`
Photos attached to reports.

| Column        | Type          | Notes                                              |
| ------------- | ------------- | -------------------------------------------------- |
| id            | bigint (PK)   |                                                    |
| report_id     | bigint (FK)   | -> `reports.id`, `ON DELETE CASCADE`               |
| storage_path  | varchar       | path to the stored file                            |
| visibility    | enum          | `public`, `private` - default `private`            |
| created_at / updated_at | timestamp |                                               |

**Indexes:** `report_id`, `visibility`

---

### `matches`
Candidate lost<->found pairings produced by the matching engine.

| Column            | Type                | Notes                                          |
| ----------------- | ------------------- | ---------------------------------------------- |
| id                | bigint (PK)         |                                                |
| lost_report_id    | bigint (FK)         | -> `reports.id`, `ON DELETE CASCADE`           |
| found_report_id   | bigint (FK)         | -> `reports.id`, `ON DELETE CASCADE`           |
| score             | smallint (unsigned) | 0-100 confidence                               |
| reasons           | jsonb               | nullable; human-readable match reasons         |
| status            | enum                | `pending`, `accepted`, `dismissed` - default `pending` |
| algorithm_version | varchar             | version of the matching algorithm              |
| created_at / updated_at | timestamp    |                                                |

**Constraints:** **unique** `(lost_report_id, found_report_id)` - **Indexes:** `status`, `score`

> Backed by model `App\Models\ItemMatch` (see naming note above).

---

### `claims`
Ownership claims with private verification data and review workflow.

| Column            | Type          | Notes                                              |
| ----------------- | ------------- | -------------------------------------------------- |
| id                | bigint (PK)   |                                                    |
| report_id         | bigint (FK)   | -> `reports.id`, `ON DELETE CASCADE`               |
| claimant_id       | bigint (FK)   | -> `users.id`, `ON DELETE CASCADE`                 |
| answers           | jsonb         | private verification answers                       |
| evidence_path     | varchar       | nullable                                           |
| status            | enum          | `submitted`, `under_review`, `approved`, `rejected`, `completed` - default `submitted` |
| reviewed_by       | bigint (FK)   | -> `users.id`, nullable, `ON DELETE SET NULL`      |
| reviewed_at       | timestamp     | nullable                                           |
| decision_reason   | text          | nullable                                           |
| created_at / updated_at | timestamp |                                                  |

**Indexes:** `report_id`, `claimant_id`, `status`, `reviewed_by`

---

### `item_events`
Append-only audit trail of actions taken on reports.

| Column        | Type          | Notes                                              |
| ------------- | ------------- | -------------------------------------------------- |
| id            | bigint (PK)   |                                                    |
| report_id     | bigint (FK)   | -> `reports.id`, `ON DELETE CASCADE`               |
| user_id       | bigint (FK)   | -> `users.id`, nullable, `ON DELETE SET NULL` (null = system event) |
| action        | varchar       | e.g. `created`, `status_changed`, `custody_transferred` |
| metadata      | jsonb         | nullable                                           |
| created_at / updated_at | timestamp |                                               |

**Indexes:** `report_id`, `user_id`, `action`

---

### `notifications`
Laravel database notifications (polymorphic).

| Column        | Type          | Notes                                              |
| ------------- | ------------- | -------------------------------------------------- |
| id            | uuid (PK)     |                                                    |
| type          | varchar       | notification class name                            |
| notifiable_type / notifiable_id | morphs | polymorphic relation to any notifiable model |
| data          | text          | JSON-encoded payload                               |
| read_at       | timestamp     | nullable                                           |
| created_at / updated_at | timestamp |                                               |

**Indexes:** morph index on `(notifiable_type, notifiable_id)`

---

## Eloquent models

| Model                            | Table                 | Key relationships                                             |
| -------------------------------- | --------------------- | ------------------------------------------------------------- |
| `App\Models\Community`          | `communities`         | hasMany memberships, belongsToMany members, hasMany reports   |
| `App\Models\CommunityMembership`| `community_memberships` | belongsTo community, belongsTo user                       |
| `App\Models\Category`           | `categories`          | belongsTo parent, hasMany children, hasMany reports           |
| `App\Models\Report`             | `reports`             | belongsTo user/community/category; hasMany photos, lostMatches, foundMatches, claims, events |
| `App\Models\ReportPhoto`        | `report_photos`       | belongsTo report                                              |
| `App\Models\ItemMatch`          | `matches`             | belongsTo lostReport, belongsTo foundReport                   |
| `App\Models\Claim`              | `claims`              | belongsTo report, belongsTo claimant, belongsTo reviewer      |
| `App\Models\ItemEvent`          | `item_events`         | belongsTo report, belongsTo user                              |
---

## Data Integrity & Constraints

The schema enforces the following rules (verified against PostgreSQL):

- **Report type** is constrained to `lost` or `found` via a `CHECK` constraint
  (`reports_type_check`).
- **Report status** is constrained to `open`, `under_review`, `resolved`,
  `rejected`, `expired` via `reports_status_check`. *Valid state transitions are
  enforced by application logic* (a service/state-machine layer), not at the DB
  level, so the set of legal next-states can evolve without a migration.
- **Indexes** on `reports`: composite `(community_id, type, status)` for the
  common "browse reports in a community" query, plus `category_id` and
  `event_at` (and `status`, `user_id`).
- **Timestamps are stored in UTC** (`config/app.php` -> `timezone` = `UTC`).
  Convert to the user local timezone for display at the presentation layer
  (e.g. Carbon `->timezone($userTz)` in API resources, or client-side).
- **Foreign keys** are defined on all relations with explicit `ON DELETE`
  behaviour (see tables above). **Unique constraints** protect slugs, report
  `uuid`, `(community_id, user_id)` memberships, and `(lost_report_id,
  found_report_id)` matches.
- **Public identifiers:** `reports.uuid` is a non-enumerable UUID used for
  public report URLs. It is *not* an authorization mechanism - authorization is
  enforced separately (policies) - but it avoids exposing sequential IDs and
  reduces easy enumeration.
- **Money (future):** if deposits/fees are added later, store monetary amounts
  as `integer` (minor units, e.g. cents) or `decimal(p, s)` - never floating
  point.