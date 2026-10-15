# Frontend (Livewire + Tailwind)

The UI is built with **Livewire v4** components and **Tailwind CSS v4**, served
through a single responsive Blade layout.

## Stack

- Livewire: `livewire/livewire` (installed via Composer)
- Tailwind CSS v4 via `@tailwindcss/vite`
- Frontend assets compiled with Vite: `npm install && npm run build`
  (produces `public/build/manifest.json`, required for `@vite` in views)

## Layout & navigation (`resources/views/components/layout.blade.php`)

Responsive top navigation with links for **Home**, **Discover**, **Create
Report**, **Dashboard**, and **Notifications** (with an unread badge). Auth-only
links are wrapped in `@auth`; a Log out action posts to `logout`. Flash messages
(`success`/`error`) render at the top.

## Livewire components (`app/Livewire/`)

### ReportList (`livewire.report-list`)
Public discovery feed. URL-bound filters (via `#[Url]`) for `search`,
`community`, `category`, and `type`, plus pagination. Only `open` reports are
listed.

### ReportCreate (`livewire.report-create`)
Step-by-step wizard (Classify -> Details -> When & Where -> Photos). Supports
photo uploads (`WithFileUploads`, up to 4, optional) and an interactive Leaflet/OpenStreetMap map for optional location selection.

**Authorization:** only communities where the user is an *active member* are
selectable, and membership is re-checked server-side on `nextStep()` and
`save()` (`abort_unless(...)`) - never trusting the client. On success it
creates the report, stores photos privately, records a `created` audit event,
and dispatches `GenerateMatches` `afterCommit`.

### MatchList (`livewire.match-list`)
Shows a report potential matches (from both the lost and found side) with the
score and explainable `reasons`.

**Authorization:** `mount()` calls `abort_unless($this->canView())` - only the
report owner or an active community moderator may view.

### ClaimManager (`livewire.claim-manager`)
- **Submit:** eligible active members (not the found-report owner) can submit
  private verification answers + optional evidence. `submitClaim()` re-checks
  `canSubmit()` server-side (`abort_unless`).
- **Review:** holders/moderators approve or reject with a required reason.
  `review()` enforces authorization via the `ClaimPolicy`
  (`$user->can('review', $claim)`), so the claimant can never review their own
  claim, and unrelated users / other-community moderators are rejected (403).

## Routes (`routes/web.php`)

| Method | URI                              | Name                 |
| ------ | -------------------------------- | -------------------- |
| GET    | `/`                              | `home`               |
| GET    | `/login`, POST `/login`          | `login`, `login.attempt` |
| GET    | `/reports`                       | `reports.index`      |
| GET    | `/reports/create`                | `reports.create`     |
| GET    | `/reports/{report}`              | `reports.show`       |
| GET    | `/reports/{report}/matches`      | `reports.matches`    |
| GET    | `/dashboard`                     | `dashboard`          |
| GET    | `/notifications`                 | `notifications`      |
| POST   | `/logout`                        | `logout`             |

(`reports/create` is registered before `reports/{report}` so it is not captured
by the wildcard.)

## Security summary

- Every state-changing Livewire action re-validates authorization server-side.
- Claim review delegates to the `ClaimPolicy` (reporter or active moderator;
  never the claimant).
- Photos and evidence are stored on the private disk.

## Testing

`tests/Feature/FrontendComponentsTest.php` exercises the components via
`Livewire::test(...)`:

- ReportList renders and filters by type & search
- ReportCreate blocks a non-member (403) and lets an active member create
- MatchList requires authorization (403 for outsiders); owner sees matches +
  reasons
- ClaimManager blocks unauthorized review (403), blocks claimant self-review,
  lets a moderator approve, and lets an active member submit a claim

Run:

```bash
php artisan test --filter=FrontendComponentsTest
```