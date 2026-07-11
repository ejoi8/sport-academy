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
> test suite), and DemoSeeder's catalog then **stacks on top** as duplicate-named programs
> (BaselineSeeder uses `firstOrCreate`, DemoSeeder doesn't) — you'll see 6 programs, not 4.
> Use exactly the two commands above for demo data.

**Logins** (all password `password`):

| Email | Role |
|---|---|
| `admin@admin.com` | super_admin (the admin) |
| `coach@coach.com` (Farid, the main coach), `amir@…`, `lena@…`, `hafiz@academy.test` | coach |
| `parent1@demo.test`, `parent2@demo.test`, … | parent (no panel access) |

Demo data: the academy's real weekend timetable — **Group** (Sabtu petang, Sat 16:00–18:00) and
**1-on-1** (Sabtu petang + Ahad pagi, Sun 09:00–11:00) recurring weekly, a one-off **Football
Clinic**, plus a retired **Ramadhan Special** (inactive, last month only). Each slot gets a cohort
sized under its capacity (Group ~32, 1-on-1 ~8/slot), in families of 4 under generated parent
accounts. Last month fully recorded; this month recorded up to today. A deterministic **scenario
layer** (`SC …`-named students on the closed Sat 11:00 Group slot) covers every credit/attendance
case — verify it with [demo-verification.sql](demo-verification.sql).

## Where things live

```
app/Filament/Resources/<Entity>/       Filament v5 NESTED layout — always:
  <Entity>Resource.php                    resource shell
  Schemas/<Entity>Form.php                form (shared with relation managers via configure() flags)
  Tables/<Entities>Table.php              table
  Pages/…, RelationManagers/…
app/Filament/Pages/RunTraining.php     the coach recording surface (the UI + in-memory roster)
app/Actions/RecordTrainingSession.php  the sole writer of attendance/scores/credits; takes a
                                         RecordSessionData DTO, returns a RecordSessionResult
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
- **Save runs through one action** — the whole write (session + attendances + scores + credit
  consumption + ad-hoc offering creation + roster pruning) lives in
  `App\Actions\RecordTrainingSession`, called with a plain `RecordSessionData` and returning a
  `RecordSessionResult`. The page only builds the DTO and refreshes badges, so a future API (e.g. a
  Vue frontend) records a session through the exact same writer.
- What Save writes, exactly: [run-training-save.md](run-training-save.md).
- Page state is URL-addressable (`?date=YYYY-MM-DD&session=<offering id>`) — refresh-safe and
  bookmarkable. Only `date` and the open session id go in the URL (never roster/search — student
  names must never reach URLs or logs); unsaved edits are **not** preserved by the URL. Future
  caveat: if sessions ever become coach-scoped, `mount()` must *authorise* the `session` param, not
  just validate that it exists.

## Payments

The published Composer package [`ejoi/payment-gateway`](https://packagist.org/packages/ejoi/payment-gateway)
(`^1.0`, installed from Packagist — see `composer.json`'s `require`) provides gateway-agnostic hosted
checkout + a manual bank-transfer flow. It's a normal vendor dependency now — bump it with
`composer update ejoi/payment-gateway`. **Never edit it in `vendor/`** — app-side code only
(`app/Http/Controllers/Payments/`, `app/Listeners/`, `app/Models/GatewayPayment.php`,
`app/Support/PaymentInstructions.php`, `app/Livewire/PublicSite/ProofUpload.php`,
`app/Filament/Resources/Payments/`, `config/payment-gateway.php`).

> History: this started as a local path package (`../_packages/payment-gateway`, `dev-main`); it was
> published to Packagist as v1.0.0 and the app now depends on the released version.

**The flow, end to end:**

1. **Checkout** — `POST /payments/enrollments/{enrollment}/checkout` (`CheckoutController`)
   creates a payment with the chosen hosted gateway (Billplz/toyyibPay/CHIP/Stripe/PayPal — only
   gateways with all required config keys filled show up as options,
   `PaymentInstructions::hostedGatewayOptions()`) and redirects to its checkout page. A **reuse
   ladder** avoids double-billing when "Pay now" is clicked more than once (stale tab, back
   button): reload the latest payment for the booking; if pending with a gateway reference,
   reconcile it first (it may already be paid); if now paid, send the parent to the return page
   instead of billing again; if still pending on the **same** gateway with a checkout URL on
   file, redirect back to that existing URL; only otherwise mint a new payment.
2. **Hosted page** — the provider collects payment, then two things happen (either can win the
   race):
   - **Webhook** — the package's own route (`payment-gateway.webhook`, registered by its service
     provider) verifies/requeries, dedupes by delivery, persists the transition, and fires
     `Ejoi\PaymentGateway\Laravel\Events\PaymentStatusChanged`.
   - **Return page** — `GET /payments/enrollments/{enrollment}/return` (`ReturnController`)
     reconciles a still-pending payment (only when it has a `gateway_reference`) before
     rendering, so a parent who lands back before the webhook arrives still sees the truth. The
     pending state has a "Check payment status again" link (reloads, which reconciles again).
3. **Activation** — `App\Listeners\ActivateEnrollmentOnPayment` (listens for
   `PaymentStatusChanged`, registered in `AppServiceProvider::boot()`) is the **only** automated
   enrolment-status change: on a paid event it activates the matching **pending** enrolment
   **only when the amount matches exactly** (`(int)` cast both sides). Two things are flagged,
   never silently swallowed, via `Log::warning()` **and** an activity-log entry
   (`activity('enrolments')->performedOn($enrollment)->log(...)`, visible on the enrolment's own
   activity trail): an **amount mismatch** (`'payment amount mismatch'`, not activated), and a
   **duplicate payment** — a paid event for an enrolment that's already `Active`
   (`'duplicate payment received'`, status left untouched). Both need manual admin review.
4. **Reconcile safety net** — the package ships
   `Ejoi\PaymentGateway\Laravel\Jobs\ReconcilePendingPayments` but doesn't schedule it;
   `routes/console.php` schedules it every 5 minutes (`withoutOverlapping()`), gated by
   `config('payment-gateway.reconcile.enabled')` (defaults true; an app-added config key, not a
   package one). This is the backstop when a webhook is missed/delayed and the parent never
   revisits the return page.
5. **Manual proof loop** — when no hosted gateway is configured, or as a "Paid by bank transfer?
   Upload your receipt" secondary option on My Family: `App\Livewire\PublicSite\ProofUpload`
   (one instance per pending online enrolment) creates a `manual`-gateway payment row (no
   provider API call — the package's manual driver just records pending) and calls
   `Payments::attachProof()` with the uploaded file (jpg/png/pdf, max 4MB). Staff review on the
   Payments resource (`app/Filament/Resources/Payments/Tables/PaymentsTable.php`): **Approve**
   (`Payments::approve()` → paid → `PaymentStatusChanged` → the listener activates) or **Reject**
   (`Payments::reject(..., reupload: true)` → stays pending, reason recorded in
   `metadata.review.note`, so the parent's dashboard shows "Your previous receipt could not be
   confirmed: …" and lets them upload again). A "View proof" action streams the file via
   `GET /payments/proofs/{proof}` (`ProofDownloadController`, staff-only — the proof disk is
   private by default, `config('payment-gateway.proofs.disk')`).
6. **Admin surface** — `app/Filament/Resources/Payments/*` (nav group **Finance**): reference,
   parent, child, program, amount (`->money('MYR', divideBy: 100)`), gateway/status badges
   (colored explicitly — the package's `PaymentStatus` enum has no Filament `HasColor`, unlike
   the app's own enums), paid-at, transaction id; filters by status/gateway; an "Enrolment" row
   action opens the linked enrolment; a header "Record offline payment" action creates +
   immediately approves a manual payment for any pending enrolment (the audited replacement for
   an undocumented manual status flip).

**Package gotcha (worked around, not patched):** `Ejoi\PaymentGateway\Laravel\Models\Payment`'s
`proofs()`/`webhooks()` relations don't pin an explicit foreign key, so Eloquent's default guess
(`Str::snake(class_basename($this)).'_id'`) resolves against whatever class is actually calling
it — since `App\Models\GatewayPayment extends Payment`, calling `$gatewayPayment->proofs()`
guessed `gateway_payment_id` instead of the real `payment_id` column. Fixed by overriding both
relations on `GatewayPayment` with an explicit foreign key. If the package is ever updated to
pin these itself, the overrides become harmless duplicates.

**Local-dev reality:** none of the gateways can reach `localhost` for their webhook callback.
For real webhook testing, tunnel your dev server over HTTPS (e.g. `expose` or `ngrok`) and point
the gateway's dashboard at the tunnel URL. Without a tunnel, activation still happens — just via
the return-page reconcile or the scheduled `ReconcilePendingPayments` job — so nothing is stuck
waiting on a webhook that can never arrive in local dev.

## Invariants — do not break these

1. **One writer of attendance, scores, and credit consumption** — the
   `App\Actions\RecordTrainingSession` action, called only by Run Training today (and any future
   API). There is deliberately no Attendance/Score Filament resource.
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
| Parent experience | **Shipped** — parents live on the public site (booking funnel + My Family); the Filament panel stays Admin/Coach only. |
| Payment gateway | **Shipped** — optional, decoupled `ejoi/payment-gateway` add-on (hosted checkout + manual bank-transfer proofs). With no gateway configured, `Enrollment.status` + manual proofs still drive activation. See **Payments** above. |
| Run Training navigation | Iterated dropdown → date-first → session-first → **date + session accordion** (current). The accordion makes date/roster desync impossible by construction. |
| Recording write path | **Extracted** to `App\Actions\RecordTrainingSession` — the Livewire page builds a DTO and calls the action, so the write logic is shared/testable and API-ready. |
| Ad-hoc sessions | Create-on-**Save** (not on start) to avoid orphan one-off offerings. |
| Cross-program make-ups | **Restricted** (2026-07-05) — make-up credits are same-program only (value mismatch: e.g. a Goalkeeper credit paying a 1-on-1 session); coach can always charge walk-in instead. See credits-policy.md |
| Monthly offering rollover | **Shipped** — a "Clone to month" bulk action on Catalog → Timeslots clones selected open recurring offerings one month forward (skipping one-offs, inactive programs, and existing matches); never creates or renews Enrollment rows (renewal always requires payment, no auto-billing). Catalog → Timeslots and People → Enrolments default their Month filter to the current period. |

## Deferred / known gaps

- **Authorization is all-or-nothing inside the panel** — any staff role reaches every resource. A
  real `super_admin` gate bypass exists, deliberately carved out so it can *not* skip the delete
  guardrails. Whether to restrict coaches out of Finance/pricing is an open call — see
  [review-findings.md](review-findings.md).
- **Quick Record / Student 360** — a second (student-first) recording flow and a coach-facing
  student page are planned but not built: [plan-quick-record.md](plan-quick-record.md).
- **Ad-hoc one-offs appear in Catalog → Timeslots** (0-capacity rows). Cosmetic; an
  `is_ad_hoc` flag + filter would hide them if it starts to grate.
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
