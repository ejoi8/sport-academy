# Execution plan — "Quick Record" (student-first Run Training) + Student 360 page

A self-contained build plan for an implementing agent (written for DeepSeek or any AI/developer
with no prior context). It adds a **second, simpler recording menu** next to the existing Run
Training so the academy can compare both flows on real sessions — and a **coach-facing Student
page** that answers "has this kid paid, and how many sessions have they used?" at a glance.

> **Before writing any code, read:** [handover.md](handover.md) (stack, invariants, conventions),
> [domain-model.md](domain-model.md), [credits-policy.md](credits-policy.md),
> [run-training-save.md](run-training-save.md). Non-negotiable ground rules: money is integer
> `*_sen`; credits are **derived** from attendance rows, never stored as counters; sessions are
> on-demand (never pre-generate schedules); permissive — flag, don't block; Filament v5 **nested**
> resource layout; run `php vendor/bin/pint --dirty` + full `php artisan test` after every slice
> and keep it green; commits are conventional style with **no AI attribution of any kind**.
> **Never run `git checkout -- .`, `git reset --hard`, or `git clean` — the working tree carries
> other in-progress work. To revert your own changes, revert only the files you created/edited.**

## Why this exists (the problem being solved)

The current Run Training page is **date → timeslot-card → roster**. It is correct but couples
the coach's simple job (mark attendance, score skills) to catalog concepts (offerings, periods,
timeslots) that overwhelm both the coach and the developer. The hypothesis to test: a coach
should just **search a student (name / IC), pick the date, tap attendance, tap scores — done**
— and see everything commercial (payment, enrolment, credits) on the **student's page**, not in
the recording flow.

**Both menus must stay installed side by side and write the SAME data**, so the academy can run
real sessions through each and keep whichever wins. Neither may break the other.

## Decisions taken (flip any of these before building if the owner disagrees)

| # | Decision | Chosen default | The alternative not taken |
|---|---|---|---|
| D1 | Where records attach | **Auto-link behind the scenes** — same tables (`training_sessions`, `attendances`, `assessment_scores`), same credit consumption; the offering is *resolved*, never shown | A new slot-free table (would fork the data: credits/`x/N`/payment tracking would stop connecting to attendance) |
| D2 | Group recording | **Search-and-stack a working list**, one Save for the whole list | One-student-at-a-time (too slow for groups) |
| D3 | Student with no enrolment / no credits | **Record anyway + flag** ("no active enrolment", "+N over") — no fee mechanics in this flow; money conversations happen outside | Hidden walk-in-fee mechanics (more magic), or blocking (violates the permissive rule) |
| D4 | Student page contents | **All four blocks**: payment & enrolment status · credits summary · attendance history · assessment progress | — |

---

# Phase 0 — Extract the recording engine (the real de-coupling)

The developer-overwhelm is mostly that `app/Filament/Pages/RunTraining.php` (~1,000 lines) mixes
UI state with business logic. Before building the new page, **extract the write-path into plain
services** consumed by BOTH pages — this is what actually decouples the business logic, and it
updates the "sole writer" invariant to: *attendance/scores/credits are written only by the
recording services* (`app/Services/Training/`).

Create (names indicative — match app conventions):

- `App\Services\Training\SessionRecorder` — the transactional save. Extract from
  `RunTraining::save()` + helpers, keeping behaviour byte-identical:
  `record(Offering $offering, string $date, array $rows, ?int $headCoachId, int $recordedBy): TrainingSession`
  where each row = `{student_id|new-student data, participant_type, status, coach_id, scores[],
  note, fee_sen, enrollment_id}` — `firstOrCreate` the session, upsert attendances, delete-and-
  bulk-insert scores, remove de-rostered players, walk-in student creation by IC (reuse the
  exact current logic: `resolveStudentId`, `writeAttendance`, `buildScoreRows`).
- `App\Services\Training\CreditResolver` — wraps the pool logic already on the models
  (`liveCreditEnrollment($programId, $maxPeriod)`, carry-over sums) so both UIs and the Student
  page compute identical numbers.
- Refactor `RunTraining.php` to call these services. **Zero behaviour change** — the entire
  existing suite must pass untouched. This phase alone is shippable.

# Phase 1 — the "Quick Record" page

`app/Filament/Pages/QuickRecord.php` + Blade (follow the Run Training pattern: custom Blade,
`rt-*`-style CSS tokens; nav group **Training & Assessment**, label "Quick Record", icon e.g.
Heroicon::Bolt, sort after Run Training).

### The coach's screen (everything else is hidden)

```
Date [2026-07-12] (defaults today)        3 players · 2 saved

Search player  [ name or IC… ]        ← live results; click adds to the list

┌ Alfie · KP 7777…            [paid 1/4]              (Present)(Late)(Absent)(Excused)  ▾ ┐
│   Passing (1)(2)(3)(4)(5)  Dribbling …   note [        ]                                │
├ Mia · KP 1500…              [no active enrolment ⚑]  (Present)(Late)(Absent)(Excused) ▾ ┤
└ Yusuf · KP 1500…            [5/4 +1 over ⚑]          (Present)(Late)(Absent)(Excused) ▾ ┘

[ Save all ]           ⚑ 2 flagged — see their student pages
```

- **No timeslot/offering/period appears anywhere on this screen.**
- Search = same semantics as Run Training's participant search (name or IC, sanitised LIKE,
  exclude already-listed). Each result row shows name · IC · age · a payment/credit chip.
- Rows expand for the 1–5 score pills (active skills) + note — reuse/adapt the existing
  `run-training-item` partial rather than re-inventing it.
- Status pills default Present; per-row remove; the flags of D3 render as chips
  (reuse the badge classes/logic).
- Date change with unsaved rows → confirm (reuse the dirty pattern).
- `?date=` in the URL (`#[Url]`), plus `?student=<id>` support: pre-adds that student (used by
  the Student page's "Record attendance" button).

### The hidden resolution algorithm (the heart of D1)

On **Save**, for each listed student, resolve *which offering* the attendance belongs to —
without asking the coach:

1. Student's **live enrolments** (`active|pending|overdue`, not expired), offering period
   **= the date's month** preferred, else `<=` it (most recent first).
2. Among those, prefer an offering whose **weekday matches the chosen date**; else the single
   live enrolment; else (multiple ambiguous) show ONE tiny inline question on the row —
   program names only, e.g. `record under: (Group Training) (Goalkeeper)` — never slot jargon.
3. Resolved → group rows by offering and call `SessionRecorder::record()` per offering for that
   date (an off-schedule date on the student's own offering is fine — the current system already
   supports and displays those). `participant_type = enrolled`, `enrollment_id` = that
   enrolment → **credits consume exactly as today**.
4. **No enrolment at all** (D3): attach to the month's **reconciliation offering** — a system
   offering `firstOrCreate`d per month: program **"Unassigned (Quick Record)"** (`is_active`
   false so it never appears in public/ad-hoc pickers), `schedule_type one_off`,
   `specific_date` = the date, `capacity 0`, `is_open false`, price 0. Row saves as
   `participant_type walk_in`, `enrollment_id null`, `walk_in_fee_sen null`, plus an
   activity-log flag (`activity('enrolments')`-style, log `quick-record unlinked attendance`)
   so the admin has a reconciliation trail. Show the ⚑ chip on the row and in the save toast
   ("Saved — 2 players need follow-up").

**Consistency guarantee:** open the current Run Training on the same date afterwards → the same
saved data appears on the offering's card (both menus read/write the same rows). State this as
an acceptance test.

# Phase 2 — the Student 360 page (coach-facing)

New `ViewStudent` page for the Student resource (Filament v5 infolist; register in
`StudentResource::getPages()`, make the table row link to view; keep edit as a header action).
Sections (D4 — all four):

1. **Header**: name, IC, age, active badge, parent + phone; actions: **Record attendance**
   (→ `QuickRecord::getUrl(['student' => $id, 'date' => today])`) and **Print report** (existing
   `students.report` route).
2. **Enrolments & payment**: each enrolment — program, schedule label, period, status badge
   (paid/pending/overdue/cancelled), price (RM via `->money('MYR', divideBy: 100)` convention),
   booking reference; related gateway payments (`GatewayPayment` by booking reference: provider,
   amount, status, paid_at).
3. **Credits**: per-enrolment `used/N` with the same 3-state colouring as the roster
   (in-progress / paid-up / +N over), carried credits per program, and the lifetime line from
   `Student::creditSummary()` (purchased · attended · owed · over).
4. **Attendance history**: reverse-chronological — date, program, status, coach, flags; include
   unlinked Quick-Record rows, visibly flagged.
5. **Assessment progress**: latest score per skill + a small trend (reuse the printable report's
   data source; a per-skill sparkline or a simple latest-vs-first delta list is enough — do not
   over-build).

# Phase 3 — comparison instrumentation (tiny, optional but requested)

- Both pages already write `attendances.marked_by`; additionally add a nullable
  `attendances.recorded_via` (`run-training|quick-record`) column (one additive migration) so
  the academy can see which menu recorded what during the trial.
- A one-line entry in `docs/demo-verification.sql`: count attendances per `recorded_via`.

## Tests (Pest, follow existing styles — `RunTrainingTest` patterns; `BaselineSeeder` seeds a
## weekend catalog with NO people, so tests create their own students/enrolments)

Phase 0: the full existing suite green with zero test edits (proves extraction is behavioural
no-op).
Phase 1:
- search adds a student; save writes a `training_session` on the student's own offering with an
  enrolled attendance consuming a credit (assert same rows the old page would write).
- weekday preference: student enrolled in two programs (Sat + Sun) → Saturday date resolves the
  Saturday offering without asking; ambiguous case surfaces the inline program choice.
- no-enrolment student → saved under the month's "Unassigned (Quick Record)" offering,
  walk_in/null fee, activity flag written; that offering is invisible to the public pages and
  the ad-hoc program picker.
- cross-check: record via QuickRecord, open RunTraining same date → hydrated card shows it
  (and vice-versa).
- flags: over-limit chip on a 4/4 student; nothing blocks saving.
- `?student=` deep-link pre-adds; `?date=` respected; unparseable date falls back to today.
Phase 2:
- ViewStudent renders all sections for a student with enrolments+payments+attendance; scoped
  correctly (no other student's data); Record-attendance button URL carries the student id.

## Explicitly out of scope (do not build)

- Removing or altering the existing Run Training (comparison requires both, unchanged).
- Fee/make-up mechanics inside Quick Record (D3: flags only).
- Parent-facing changes; planned/pre-generated sessions (declined, on record); any package
  (`ejoi/payment-gateway`) modification.

## Acceptance checklist

- [ ] A coach can record a known student's attendance + scores in ≤ 4 interactions with no
      timeslot vocabulary on screen.
- [ ] The saved data is indistinguishable from a Run Training save for enrolled students
      (credits, badges, reports all agree).
- [ ] Unlinked recordings are impossible to lose: flagged on the row, in the toast, on the
      student page, and in the activity log.
- [ ] The Student page answers "paid? how many used? progressing?" without opening anything else.
- [ ] Full test suite green; both menus co-exist; deleting the Quick Record page later would
      leave zero orphaned data concepts (only the reconciliation offerings, documented).
