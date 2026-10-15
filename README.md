# FindBack - Smart Lost & Found Network

FindBack is a community-driven lost & found platform. Members of a
**community** (a university, transit network, shopping mall, workplace, etc.)
post **reports** about items they **lost** or **found**. The system uses an
automated matching engine to pair likely lost<->found reports, and a
verification/claim workflow so items are returned to their rightful owners
through an auditable chain of custody.

- **Backend:** Laravel 13 (PHP 8.3) - located in `backend/`
- **Database:** PostgreSQL 15
- **Package manager:** Composer

---

## Features

- Communities - scoped spaces (university, transport, mall, ...) with
  membership roles (`member`, `moderator`, `admin`).
- Reports - lost/found item posts with category, location, event time,
  photos, and expiry.
- Automated matching - candidate lost<->found pairs scored 0-100 with
  human-readable reasons and an algorithm version.
- Claims & verification - claimants answer private verification
  questions; moderators approve/reject with an audit trail.
- Audit trail - every report action (created, status_changed,
  custody_transferred, ...) is recorded in `item_events`.

---

## Repository layout

```
FindBack/
+-- backend/                 # Laravel 13 application
|   +-- app/Models/          # Eloquent models
|   +-- database/migrations/ # Database schema (migrations)
|   +-- ...
+-- docs/                    # Project documentation
|   +-- DATABASE.md          # Full schema reference (tables, columns, FKs)
|   +-- SETUP.md             # Local development setup guide
|   +-- REPORTING.md         # Reporting workflow (store lost/found reports)
|   +-- MATCHING.md          # Smart matching engine & async processing
|   +-- CLAIMS.md            # Claims & handover workflow
|   +-- NOTIFICATIONS.md     # Notifications & audit trail
|   +-- TESTING.md           # Testing guide & coverage
|   +-- FRONTEND.md          # Livewire UI & layout
|   +-- GIT_WORKFLOW.md      # Branching & protection conventions
+-- README.md                # This file
```

---

## Quick start

See `docs/SETUP.md` for the full guide. In short:

```powershell
# 1. Install PHP dependencies
cd backend
composer install

# 2. Configure environment (database credentials)
copy .env.example .env
php artisan key:generate

# 3. Run migrations against PostgreSQL
php artisan migrate
```

---

## Tech stack

| Component      | Version / Tool                |
| -------------- | ----------------------------- |
| Language       | PHP 8.3                       |
| Framework      | Laravel 13                    |
| Database       | PostgreSQL 15                 |
| ORM            | Eloquent                      |
| CLI tooling    | Artisan, Composer             |

---

## Documentation

- [Database schema](docs/DATABASE.md) - tables, columns, relationships, indexes
- [Local setup](docs/SETUP.md) - install PHP, Composer, PostgreSQL & run the app
- [Reporting workflow](docs/REPORTING.md) - store lost/found reports (validation, photos, audit, async matching)
- [Matching engine](docs/MATCHING.md) - weighted scoring, async match generation, notifications
- [Claims & handover](docs/CLAIMS.md) - submit, review (policy), and finalize item handover
- [Notifications & audit](docs/NOTIFICATIONS.md) - user notifications and item_events audit helper
- [Testing](docs/TESTING.md) - test setup, coverage, and how to run the suite
- [Frontend](docs/FRONTEND.md) - Livewire components, layout, navigation, and UI security
- [Git workflow](docs/GIT_WORKFLOW.md) - branches, protection, PR process

## License

The Laravel framework is open-sourced software licensed under the
[MIT license](https://opensource.org/licenses/MIT).