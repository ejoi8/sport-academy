# Design note — planned sessions, multi-weekday & unfinished-slot tracking

**Status — Part A shipped; Parts B–D deferred.** Decided (2026-07-03) that the on-demand model
plus Part A's credit indicators are enough, and planned scheduling adds too much to manage.
Parts B–D are kept below as a record of the direction, **not** a backlog. See
[domain-model.md](domain-model.md) for how things work today.

## The problem

Sessions are on-demand: `session_count` is a credit count, the `weekday` only *hints* dates,
and a `training_session` row is created only when a coach saves. That's fine at 4 credits on a
weekly slot (the cadence tallies), but it breaks down when `session_count` diverges from the
weekly count:

- **No coach-facing progress.** The only cue that a slot has "more to run" is the per-student
  `x / N` badge — seen after the fact. Nothing says "this slot: 3 of 8 done."
- **Nowhere to place > ~4 dates.** A single weekday yields ~4 occurrences a month. Eight
  credits can't map to eight fixed dates without more weekdays.
- **Silent under-delivery.** Set 8 credits, run 4 — everyone freezes at `4 / 8` and no one is
  told.

These are three coupled pieces. Below, cheapest first.

---

## Part A — Unfinished-slot list ✅ shipped

A report of students who still hold unused credits. **Fully derivable from existing data** —
`sessions_included` minus consumed attendances (present/late/absent). No migration.

**Practical surfaces (pick one or both):**

1. **A filter on the Enrolment resource** — "Credits remaining", plus sort by remaining. The
   resource already shows the `used / total` column, so this is the smallest possible add and
   gives a filterable, sortable list for free.
2. **A dashboard widget** — "Students with unused credits" (same shape as the overdue
   follow-up widget): student · timeslot · `2 / 8` · remaining. Good for an at-a-glance chase
   list for coaches/admins.

**The query.** Count consuming attendances, then compare to the grant:

```php
Enrollment::query()
    ->whereIn('status', ['active', 'pending', 'overdue'])
    ->withCount(['attendances as used_credits' => fn ($q) =>
        $q->whereIn('status', Enrollment::CREDIT_CONSUMING_STATUSES)])
    ->havingRaw('used_credits < sessions_included');
```

**Notes.**
- Because credits **never expire**, this list *accumulates across months* — it doubles as a
  "who's owed sessions / outstanding liability" view. Worth a period filter so it can be
  scoped to the current month if the list gets long.
- At SaaS scale a `withCount + havingRaw` over every enrolment gets heavy; if that bites,
  denormalise a `credits_used` counter on the enrolment (kept in sync when attendance is
  written) and filter on a plain column. Not needed for MVP.

**Shipped** as the "Has credits remaining" + dynamic "Sessions attended" filters (plus a Month
filter and sortable credits column) on the Enrolment resource, and the unused-credits dashboard
widget. Implemented with a portable **correlated subquery** rather than `havingRaw` so it runs
on SQLite (tests) as well as MySQL.

---

## Part B — Multi-weekday offerings (deferred)

So a slot can place more than ~4 dates a month.

- **Schema:** `offerings.weekday` (single) → `offerings.weekdays` (JSON array of ISO days,
  e.g. `[1, 3]` for Mon+Wed). JSON is enough for MVP; a pivot table only if we later need to
  query "all Wednesday slots" efficiently.
- **Touches:** `scheduleLabel()`, the date-snap logic (`defaultDateForOffering`), the form
  (a multi-select), and date generation below.
- **Migration:** backfill existing `weekday` into `weekdays`, then drop `weekday`.

---

## Part C — Planned sessions (deferred)

Pre-create the month's dates so the coach opens *"Wednesday's class"* from a plan instead of
creating it from scratch.

- **Schema:** `training_sessions.status` — `planned` | `completed` (or derive *completed* =
  "has attendances"; an explicit column is clearer for reporting).
- **Generation:** for each weekday in `weekdays`, each occurrence in the offering's month → a
  `planned` session. Triggered by a **"Generate schedule" action** on the offering (explicit
  beats magic-on-create, and re-runnable). Uses `firstOrCreate` so it's idempotent and never
  clobbers a session that already ran.
- **Run Training UX:** the coach picks a timeslot and sees its **month schedule with
  progress** — `Jul: ✔ 2 · ✔ 9 · ○ 16 · ○ 23 — 2 of 4 done`. Opening a date loads the usual
  roster. **Extra, unplanned sessions are still allowed on demand** (nothing here hard-blocks).
- **Progress signal:** per timeslot, `completed / planned` — the "how does the coach know"
  answer, surfaced on the Run Training header and optionally the dashboard.

---

## Part D — `session_count` ↔ dates match check (deferred)

When an offering is saved, if `session_count` ≠ the number of dates the schedule would
generate for the month, **warn** (e.g. "8 credits but this schedule runs 4 sessions in Jul").
A nudge, **not a block** — per the "indicators, not hard blocks" rule, and because odd cases
are entered by hand.

---

## Phasing

| Phase | Scope | Status |
|---|---|---|
| **1** | Part A — unfinished-slot filter + widget | ✅ **shipped** |
| **2** | Part B + C — multi-weekday, planned sessions, progress | **Deferred** — adds ongoing management |
| **3** | Part D — match-check warning | **Deferred** (follows B/C) |

## Open questions (only relevant if B–D are ever revived)

- Who triggers generation — a button on the offering, or auto when it's opened?
- Can a coach reschedule / cancel a *planned* (un-run) session? (Likely yes — delete or move.)
- Does clone-to-month also clone the generated schedule, or just the offering?
- Should `session_count` become *derived* from the schedule (count of dates), or stay a manual
  snapshot with the match-check as the guard? (Leaning: stay manual + warn, for offline cases.)
