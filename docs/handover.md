# Handover — Football Academy

A management app for a football academy: register students, sell monthly training slots,
record attendance and per-skill assessment scores at every session. Designed to flex to other
sports (Sport → Program → Skill rubric are all data, not code).

**Start here:** read [domain-model.md](domain-model.md) before touching enrolments, offerings,
or Run Training — several rules are deliberately non-obvious (credits vs schedule, on-demand
sessions, snapshot-at-enrolment).

## Stack

| | |
|---|---|
| Framework | Laravel 13 (PHP ^8.3) |
| Admin panel | Filament v5, single panel at **`/app`** (`AppPanelProvider`) |
| Auth / roles | Spatie Permission via **filament-shield v4** (`super_admin`, `admin`, `coach`, `parent`) |
| DB | MySQL in dev (`football_academy`); SQLite `:memory:` in tests |
| Tests | Pest v4 (`php artisan test`) — Livewire-driven feature tests |
| Formatting | Laravel Pint (`php vendor/bin/pint --dirty`) |

## Getting started

```bash
composer install
cp .env.example .env && php artisan key:generate   # point DB_* at MySQL
php artisan migrate:fresh --force
php artisan db:seed --class="Database\Seeders\DemoSeeder" --force
```

> **Seeder gotcha:** do **not** combine `migrate:fresh --seed` with a later `DemoSeeder` run.
> `--seed` runs `DatabaseSeeder` → `BaselineSeeder` (a lean 2-program baseline used by the
> test suite), and DemoSeeder's catalog then **stacks on top** (you'll see 6 programs).
> Use exactly the two commands above for demo data.

**Logins** (all password `password`):

| Email | Role |
|---|---|
| `admin@admin.com` | super_admin |
| `coach@academy.test` (Farid), `amir@…`, `lena@…` | coach + super_admin |
| `parent1@demo.test` … `parent100@demo.test` | parent (no panel access) |

Demo data: 5 programs — **1-on-1** (Wed 18:00), **Group Training** (Sat 09:00),
**Goalkeeper** (Sun 09:00) recurring weekly, a one-off **Football Clinic** (2nd Saturday
14:00) — 100 students each, in families of 4 under 100 parents — plus a retired
**Ramadhan Special** (inactive, last month only). Last month fully recorded; this month
recorded up to today. A deterministic **scenario layer** (`SC …`-named students on the closed
Sat 11:00 slot) covers every credit/attendance case — verify it with
[demo-verification.sql](demo-verification.sql).

## Where things live

```
app/Filament/Resources/<Entity>/       Filament v5 NESTED layout — always:
  <Entity>Resource.php                    resource shell
  Schemas/<Entity>Form.php                form (shared with relation managers via configure() flags)
  Tables/<Entities>Table.php              table
  Pages/…, RelationManagers/…
app/Filament/Pages/RunTraining.php     the coach recording surface (sole writer of attendance/scores)
resources/views/filament/pages/        run-training.blade.php + partials/ (recorder, item)
app/Models/                            Program, Offering, Enrollment, TrainingSession, Attendance, …
database/seeders/                      BaselineSeeder (tests) · DemoSeeder (rich demo)
docs/                                  this file + domain model + design notes
```

Nav groups: **Catalog** (Sports / Programs / Timeslots / Skill Categories / Skills),
**People** (Students / Enrolments), **Training & Assessment** (Run Training), Dashboard
(role-aware widgets: admin sees stats/trends/capacity/follow-up, coach sees their slots/score
trend; parents see nothing — panel is staff-only via `canAccessPanel`).

## Run Training (the core screen)

Date → **accordion of that day's sessions** (one card per timeslot that runs that date, with
enrolled count + Saved/Not-recorded pill) → expand a card to record: coach-for-all strip,
enrolled roster (present by default, in-memory until Save), walk-ins/make-ups, per-skill 1–5
score pills, notes. One card open at a time; unsaved edits lock the rest.

- **"＋ Create new session" card** = ad-hoc/off-schedule session: pick program + start/end
  time; the one-off offering is created **inside the Save transaction** (abandoning stages
  nothing). Soft time-overlap warning, never a block.
- Deleting a saved one-off session also removes its now-empty one-off offering.
- What Save writes, exactly: [run-training-save.md](run-training-save.md).
- Page state is URL-addressable (`?date=YYYY-MM-DD&session=<offering id>`) — refresh-safe and
  bookmarkable. Only `date` and the open session id go in the URL (never roster/search — student
  names must never reach URLs or logs); unsaved edits are **not** preserved by the URL. Future
  caveat: if sessions ever become coach-scoped, `mount()` must *authorise* the `session` param, not
  just validate that it exists.

## Invariants — do not break these

1. **Run Training is the only writer** of attendance, scores, and credit consumption. There is
   deliberately no Attendance/Score Filament resource.
2. **Credits are derived, never stored** — `creditsUsed()` counts attendances
   (present/late/absent consume; excused doesn't). No counter columns. Full
   SOP/TNC-facing rules (carry-over, make-ups, over-delivery): see
   [credits-policy.md](credits-policy.md).
3. **Enrolment snapshots** — `price_sen` / `sessions_included` are copied from the offering at
   registration; later offering edits must not touch existing enrolments.
4. **Money is integer sen** (`*_sen`); RM only at the display edge
   (`formatStateUsing` / `dehydrateStateUsing`, tables `->money('MYR', divideBy: 100)`).
5. **Sessions are on-demand** — no pre-generated schedule rows, ever (see decisions below).
6. **Permissive by design** — warn, don't hard-block. Rare multi-month/package deals are
   handled offline and entered by hand; enrolment/attendance flows must keep accepting them.

## Decisions log (why things are the way they are)

| Decision | Status |
|---|---|
| Planned sessions / multi-weekday offerings (pre-generated schedules) | **Declined** (2026-07-03) — adds management burden; do not re-propose. Record: [design-planned-sessions.md](design-planned-sessions.md) |
| Parent experience | **Frontend-only** (public site, later phase). Filament panel is Admin/Coach only. |
| Payment gateway | **Deferred** to a late phase as an optional, decoupled add-on; `Enrollment.status` (active/pending/overdue) is the manual payment signal meanwhile. |
| Run Training navigation | Iterated dropdown → date-first → session-first → **date + session accordion** (current). The accordion makes date/roster desync impossible by construction. |
| Ad-hoc sessions | Create-on-**Save** (not on start) to avoid orphan one-off offerings. |
| Cross-program make-ups | **Restricted** (2026-07-05) — make-up credits are same-program only (value mismatch: e.g. a Goalkeeper credit paying a 1-on-1 session); coach can always charge walk-in instead. See credits-policy.md |

## Deferred / known gaps

- **Enrolment guardrails** — lock `sessions_included` once attendance exists, block
  force-delete, audit trail. Accepted risk while solo-operated.
- **Ad-hoc one-offs appear in Catalog → Timeslots** (0-capacity rows). Cosmetic; an
  `is_ad_hoc` flag + filter would hide them if it starts to grate.
- **Parent booking funnel / public site** — not started.
- **Unused-credits list accumulates across months** (credits never expire) — a period filter
  exists on the Enrolment resource; revisit if the dashboard widget gets long.

## Testing & conventions

- `php artisan test` — full suite (feature tests seed `BaselineSeeder` or `DemoSeeder`;
  dashboard tests re-seed the demo, so keep DemoSeeder fast — password hashing is done
  **once** and reused; bulk role-insert for parents).
- Pint before committing: `php vendor/bin/pint --dirty`.
- Commits: conventional-commit style (`feat(run-training): …`), grouped by concern.
- Filament v5 **nested** resource layout is mandatory (Schemas/ + Tables/ subfolders) — don't
  regress to the flat v3 shape.
