# Execution plan — next three features

A self-contained build plan for the next slices, written so **any developer or AI agent can
execute it without prior context**. Work the phases in order — each is independently shippable.

> **Before writing any code, read:** [handover.md](handover.md) (stack, invariants, conventions),
> [domain-model.md](domain-model.md) (how credits/offerings/enrolments relate),
> [credits-policy.md](credits-policy.md) (the business rules the UI must reflect).

## Ground rules for the implementing agent

1. **Never break the invariants** in handover.md — especially: Run Training is the *only* writer
   of attendance/scores/credit consumption; credits are derived, never stored as counters;
   money is integer `*_sen`; sessions are on-demand (no pre-generated schedule rows — planned
   sessions were explicitly declined, do not re-propose).
2. **Permissive by design** — warn, don't hard-block… *except* where this plan explicitly says
   "block": those cases protect data integrity (history loss), not business flexibility.
3. Filament v5 **nested** resource layout (Schemas/ + Tables/ subfolders). Single staff panel at
   `/app`; **parents never get panel access** (`canAccessPanel` already excludes them).
4. After every slice: `php vendor/bin/pint --dirty` and full `php artisan test` (79 passing at
   the time of writing) — keep it green, add tests per the checklists below.
5. Commits: conventional style (`feat(booking): …`), grouped by concern, authored by the repo
   owner only — **no tool/AI attribution of any kind**.
6. Demo data: extend `DemoSeeder` (deterministic — scripted data lives in *last* month) and
   `docs/demo-verification.sql` when a phase adds new states worth auditing.

---

# Phase 1 — Enrolment guardrails

**Goal:** once delivery has started, the integrity-critical records can't be silently corrupted
or erased. Small, ships in a day.

**Why now:** deleting an Offering currently **cascades** to enrolments and training sessions —
one admin click can erase a month of attendance history. `sessions_included` is editable after
credits were consumed, which retroactively changes what "paid up" means.

### 1.1 Lock the snapshot fields once consumed
- On the Enrolment form (`app/Filament/Resources/Enrollments/Schemas/EnrollmentForm.php`):
  disable `sessions_included` and `price_sen` when the record has ≥1 consuming attendance
  (`$record?->creditsUsed() > 0`), with helper text:
  *"Locked — sessions have been recorded against this enrolment. Adjust by cancelling and
  creating a new enrolment, or record the deal offline."*
- Do **not** add an override toggle. Odd deals are new enrolments (matches the offline-deals
  rule).

### 1.2 Block history-destroying deletes
- **Enrolment delete**: block when it has attendances. Message: *"This enrolment has recorded
  sessions. Cancel it instead (history is kept)."* Implement in the Filament delete action
  (`->before()` returning a danger notification + halt) *and* in `EnrollmentPolicy::delete()`
  so it holds everywhere.
- **Offering delete**: block when it has enrolments OR training sessions (same dual
  implementation). Suggest closing registration (`is_open = false`) instead.
- **Student delete**: block when they have attendances; suggest `is_active = false`.
- Note: Run Training's own one-off cleanup (`deleteSession()` removing an empty ad-hoc
  offering) must keep working — it deletes only when **no** sessions remain, so the policy
  check passes; add a regression test proving it.

### 1.3 Audit trail (enrolments only, keep scope tight)
- `composer require spatie/laravel-activitylog`; log Enrollment created/updated/deleted with
  changed attributes (`LogsActivity` trait, `logOnlyDirty`, log name `enrolments`).
- Read-only "Activity" relation manager (or simple table) on the Enrolment resource showing
  who changed what, when. No custom UI beyond that.

### 1.4 Tests
- Locked fields are disabled when credits consumed; editable when not.
- Enrolment/Offering/Student deletes blocked in the described states; allowed when clean.
- Run Training ad-hoc cleanup still deletes its empty one-off offering.
- Activity rows written on enrolment create/status change.

**Acceptance:** an admin cannot destroy attendance history from the panel; odd deals still
enter the system as new records; suite green.

---

# Phase 2 — Parent-facing public site + booking funnel

**Goal:** parents discover programs and book online **without** the panel. Payment stays
manual in this phase (bank transfer / DuitNow QR shown as instructions; the admin confirms) —
Phase 3 plugs a gateway into the same funnel.

**The recommended UX — keep it to four screens, phone-first:**

```
1. BROWSE      /              programs as cards: name, price/month, schedule, "seats left"
2. PICK        /programs/{p}  this month's + next month's classes for that program → "Book"
3. BOOK        /book/{offering}   one Livewire multi-step form:
                 step 1  your child   (logged-in: pick existing child or add; guest: child form)
                 step 2  your account (guest: name/email/phone/password inline; or login)
                 step 3  review + agree to the session-credit rules (link credits-policy content)
                 step 4  done → booking reference + payment instructions, status PENDING
4. MY FAMILY   /family        children · enrolments with status + credits (x/N used) ·
                              payment instructions for anything pending
```

Plain-language rules shown at step 3 (source: credits-policy.md): what a credit is, absent
consumes / excused doesn't, carry-over, same-program make-ups. The parent ticks "I understand".

### 2.1 Architecture
- Public routes in `routes/web.php`, Blade + Livewire v4 components under
  `app/Livewire/Public/…` + `resources/views/public/…`. Tailwind (already available via
  Filament's build) — simple, clean, mobile-first; no SPA framework.
- **Reuse the existing tables.** Parents are `users` with the `parent` role (100 seeded
  already); children are `students.parent_id`; a booking **is** an `Enrollment` with
  `status = pending`.
- Auth: standard Laravel auth for the public side (login/register/forgot). Registering via the
  funnel creates the user + assigns `parent` role. Panel remains closed to them.

### 2.2 Small migrations (all nullable/additive — no breaking changes)
- `enrollments.source` string default `'admin'` (`admin` | `online`) — so staff can filter
  online bookings.
- `enrollments.booking_reference` nullable string (e.g. `BK-2026-000123`), set for online
  bookings; show it on the done screen, the family page, and the Enrolments table.
- `users.phone` nullable (parents' contact) if not already present.

### 2.3 Business rules for the funnel
- **Seats left** = `capacity − count(enrolments in [active, pending])` — pending holds a seat
  (prevents overselling while payment is confirmed). Show "3 seats left" / "Full".
- **Full class ⇒ block online booking** with "Class full — contact us" (public can be stricter
  than the permissive admin side; the admin can still over-enrol manually).
- **Duplicate guard:** one enrolment per child per offering — friendly message if it exists.
- Show current month + next month offerings only (`period` filter); a class closed for
  registration (`is_open = false`) never appears publicly.
- **Pending hygiene:** a "Pending > 7 days" filter on the admin Enrolments table (follow the
  existing filter patterns). No auto-cancel — the admin decides (permissive rule).

### 2.4 Parent "My family" page (read-only MVP)
- Children with their enrolments: program, schedule label, status badge
  (pending = "awaiting payment" + instructions), credits `used/N` and carried count — reuse
  the wording from the roster badges, and `Student::creditSummary()` for the lifetime line.
- Explicitly **not** in MVP: assessment scores/progress charts for parents (a later slice —
  needs a decision on how much coaches want parents to see).

### 2.5 Notifications (log driver in dev)
- `BookingReceived` (to parent: reference + payment instructions) and `BookingConfirmed`
  (when the admin flips pending → active). Laravel notifications, mail channel; queueable but
  sync is fine at this scale.

### 2.6 Security / privacy checklist
- Policies: a parent sees/edits **only their own children and enrolments** — every public
  query scoped by `parent_id = auth()->id()`; add tests proving cross-parent access fails.
- Never expose other students' names on public pages; no student names in URLs (ids only).
- Rate-limit the booking submit + auth routes (`throttle` middleware).

### 2.7 Tests
- Funnel happy path (guest → account created → child created → pending enrolment with
  reference + seat held).
- Logged-in parent books an existing child; duplicate booking rejected; full class rejected.
- Seats-left math counts pending; closed/other-month offerings not listed.
- Parent A cannot see parent B's family page data (403/redirect).
- Admin confirming payment flips status and (if implemented) sends `BookingConfirmed`.

**Acceptance:** a parent on a phone can go from landing page to "booked, here's how to pay" in
under three minutes without staff help; staff see the booking as a normal pending enrolment.

---

# Phase 3 — Payment gateway (optional, decoupled)

> **Status: largely built** (2026-07-05), via the local `ejoi/payment-gateway` package rather
> than the bespoke design sketched below — the package already covers gateway abstraction,
> persistence, webhook verify/dedupe, and the manual/proof flow, so this phase became
> *integrating* it instead of building it from scratch. See docs/handover.md's "Payments"
> section for the as-built flow. **What this pass added/fixed** on top of the existing
> integration:
> - Fixed a fatal on every gateway return (`ReturnController` called a Livewire-only `->layout()`
>   method that doesn't exist on a plain controller's `View`) — every browser return was 500ing.
> - Scheduled the package's `ReconcilePendingPayments` job (nothing did before — a missed webhook
>   left payments pending forever) and added a "Check payment status again" link on the pending
>   return state.
> - Closed the double-payment window at checkout: a reuse ladder now reconciles/reuses an
>   existing pending payment's checkout URL instead of minting a new bill on every "Pay now"
>   click.
> - Made amount-mismatch and duplicate-payment cases visible (`Log::warning` + an activity-log
>   entry on the enrolment) instead of silently returning — previously an admin had no way to
>   discover either without reading logs.
> - Built the manual bank-transfer proof loop end to end: parent upload UI
>   (`App\Livewire\PublicSite\ProofUpload`), admin Approve/Reject actions with a proof viewer on
>   the Payments resource, and a reject-with-resubmission state shown back to the parent.
> - Polished the Payments resource (explicit status badge colors — the package's `PaymentStatus`
>   enum isn't Filament-aware) and documented the local-dev webhook limitation (gateways can't
>   reach `localhost`; use a tunnel, or rely on the return-page/scheduled reconcile).
>
> Refunds remain out of scope (unchanged from the original plan below).

**Goal:** the funnel's "how to pay" step gains a **Pay now (FPX)** button. The gateway is an
optional add-on: with it disabled, Phase 2's manual-transfer flow keeps working unchanged.

**Provider guidance (Malaysia, RM):** pick ONE of **ToyyibPay** or **Billplz** — both do FPX
bank transfer with simple REST APIs and low flat fees, well-suited to a small academy. Build
gateway-agnostic regardless (interface below) so switching later is one class.

### 3.1 Design — keep money separate from enrolment
- New `payments` table: `id, enrollment_id (FK), amount_sen, provider, provider_ref,
  status (pending|paid|failed|expired), paid_at nullable, payload json nullable, timestamps`.
  One enrolment may have many payment attempts; **never** store card/bank data.
- `App\Services\Payments\PaymentGateway` interface:
  `createBill(Enrollment $e): PaymentRedirect` (returns provider URL + our payment row) and
  `handleCallback(Request $r): Payment` (verifies signature, returns the updated payment).
  One concrete class per provider; bind via config.
- Config `config/academy.php`: `'gateway' => env('ACADEMY_GATEWAY')` — `null` disables the
  whole feature (funnel shows only manual instructions). Keys/secrets via `.env` only.

### 3.2 The one automated status transition
- Webhook route `POST /payments/callback/{provider}` (CSRF-exempt, signature-verified,
  idempotent — replaying the same callback must not double-process).
- On verified `paid` **and amount matches `enrollment.price_sen`**: mark payment `paid`,
  flip the enrolment `pending → active`, fire `BookingConfirmed`. That flip is the **only**
  automated enrolment-status change in the system — everything else stays manual. Amount
  mismatch ⇒ record as paid-with-flag, notify admin, do NOT activate (manual review).
- Also implement the browser return URL (thank-you page that *polls/reads* payment status —
  never trust the redirect alone; the webhook is the source of truth).

### 3.3 Admin surface
- Read-mostly `Payments` Filament resource (nav: People or a new Finance group): reference,
  parent, child, enrolment, amount (RM via `->money('MYR', divideBy: 100)`), provider status,
  paid_at; filter by status. A manual "Mark as paid (offline)" action that records a manual
  payment row + activates — replacing today's undocumented status flip with an audited one.
- Refunds are **out of scope** — handled offline; note it on the resource.

### 3.4 Tests
- Fake-gateway implementation for the suite (no HTTP): createBill returns a redirect,
  callback happy path activates enrolment exactly once (idempotency test: replay = no-op).
- Signature failure ⇒ 403, nothing written. Amount mismatch ⇒ flagged, not activated.
- Gateway disabled ⇒ funnel renders manual instructions only; nothing 500s.
- Sandbox smoke-test steps for the chosen provider documented in the PR description.

**Acceptance:** with the gateway off, nothing changed; with it on, a parent can pay by FPX and
their booking self-activates within seconds — and a replayed/forged webhook can't corrupt
anything.

---

## Suggested commit plan

| Phase | Commits |
|---|---|
| 1 | `feat(guardrails): lock consumed enrolment snapshots & block history-destroying deletes` · `feat(guardrails): enrolment audit trail` |
| 2 | `feat(public): landing + program pages` · `feat(booking): multi-step funnel with pending enrolments` · `feat(public): parent family page + notifications` |
| 3 | `feat(payments): payments table + gateway abstraction` · `feat(payments): {provider} FPX integration + webhook` · `feat(payments): admin payments resource + manual mark-paid` |

## Explicitly out of scope (decided — do not build)

- Planned/pre-generated sessions or multi-weekday offerings (declined 2026-07-03).
- Parent access to the Filament panel; parent-visible assessment scores (needs a decision).
- Automated refunds, auto-cancelling stale pending bookings, waitlists.
- Cross-program make-up credits (restricted 2026-07-05 — see credits-policy.md).
