# Football Academy

Management app for a football training academy — a **Filament staff panel** (admin + coaches) plus a
**public booking site**. Coaches run training and assess players; parents register their children and
follow their progress.

- **Stack:** Laravel 13 · Filament v5 · Livewire · Tailwind (Vite) · Spatie Permission (Shield) · Pest.
- **Payments:** hosted checkout + bank-transfer via the [`ejoi/payment-gateway`](https://packagist.org/packages/ejoi/payment-gateway) package.
- **Staff panel:** `/app` (admin & coaches only — parents live on the public site).
- **Coach console:** a mobile-first Home / Training / Students surface coaches land on after login,
  with printable per-student progress reports.
- **Branding:** the academy name is env-driven — set `APP_NAME` in `.env`, no template edits.

## Getting started

**Prerequisites:** PHP 8.3+, Composer, Node 18+ & npm, and a database. SQLite works out of the box;
MySQL/MariaDB (e.g. via Laragon) is fine too.

```bash
# 1. Dependencies
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Database
#    SQLite (default): the file is created automatically on first migrate.
#    MySQL: set DB_CONNECTION=mysql and the DB_* values in .env instead.

# 4. Migrate + seed  (see "Seeding" for what each option gives you)
php artisan migrate:fresh --seed

# 5. Build the Filament theme + front-end assets
npm run build          # or `npm run dev` for watch mode during development

# 6. Serve
php artisan serve      # http://localhost:8000  (or use your Laragon vhost)
```

Then open the staff panel at **`/app`** and sign in (see [Logins](#logins)).

## Seeding

Three seeders — pick the one that fits what you're doing:

| Seeder | Command | What you get | Students |
| --- | --- | --- | --- |
| **Baseline** _(default)_ | `php artisan migrate:fresh --seed` | Roles, rubric, the 3 programmes, **this month's** open slots, an admin + a coach login | none |
| **Launch** | `php artisan migrate:fresh` then<br>`php artisan db:seed --class="Database\Seeders\LaunchSeeder"` | Launch-ready: the 3 programmes, open slots for **this + next month**, an admin + the 4-coach team | none |
| **Demo** | `php artisan migrate:fresh` then<br>`php artisan db:seed --class="Database\Seeders\DemoSeeder"` | A living **~5-year** dataset: hundreds of students, enrolments, recorded sessions + rubric scores, anchored to today | ~380 |

- **Launch** is the real "open for registration, no students yet" starting point for a new academy.
- **Baseline** and **Launch** are **idempotent** (safe to re-run). **Demo** is **not** — always
  `migrate:fresh` before re-seeding it. Demo is heavier (~90k score rows) but seeds in a few seconds.

## Logins

Every seeded account uses the password **`password`**.

| Role | Email | Seeded by |
| --- | --- | --- |
| Admin (super-admin) | `admin@admin.com` | all |
| Main coach | `coach@coach.com` | all |
| More coaches | `amir@` · `lena@` · `hafiz@academy.test` | Launch, Demo |
| Parent | `parent1@demo.test`, `parent2@…`, … | Demo only |

> `admin@admin.com` is the **super-admin** (full panel); `coach@coach.com` is a **coach** (the coach
> console, not the admin resources). Manage offerings, users, etc. as the admin.

## Testing

```bash
php artisan test
```

## Project docs

- [Handover](docs/handover.md) — **start here**: stack, setup, logins, invariants, decisions
  log, and known gaps.
- [Domain model & core concepts](docs/domain-model.md) — how programs, offerings, enrolments,
  session credits, and on-demand training sessions fit together (read before touching Run
  Training or enrolments).
- [Run Training — what "Save" writes](docs/run-training-save.md) — exact persistence reference
  for the recording screen.
- [Design note — planned sessions](docs/design-planned-sessions.md) — a considered-and-declined
  direction, kept as a record (Part A shipped; B–D are not a backlog).
- [Session credits — policy (SOP/TNC source)](docs/credits-policy.md) — the plain-English rules
  for credits, carry-over, and make-ups that Run Training enforces automatically.
- [Code review — findings & decisions](docs/review-findings.md) — the last review's findings and
  the decisions taken (all patched).
- [Execution plan — Quick Record](docs/plan-quick-record.md) — student-first recording menu
  (side-by-side comparison with Run Training) + coach-facing Student 360 page. **Not yet built.**
