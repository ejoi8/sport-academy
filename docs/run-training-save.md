# Run Training — what "Save" writes

Quick reference for how recording a session persists data. **Run Training is the only writer of
attendance, scores and credit consumption.**

The page is a **date → session accordion**: pick a date, every timeslot that runs on it appears
as a collapsible card (with a Saved / Not-recorded pill), plus a "＋ Create new session" card.
Expanding a card loads its roster; nothing below is written until **Save**.

## Tables written on Save

| Table | When | What |
|---|---|---|
| `offerings` (timeslot) | **only for a *new* session** | The one-off timeslot, created **on Save** (create-on-save). An existing timeslot is untouched. |
| `training_sessions` | always, 1 row | The session: `offering_id` + `session_date` + lead `coach_id`. |
| `students` | only for a **brand-new walk-in** | A new student row when the typed person has no matching IC; existing students are reused. |
| `attendances` | 1 row per roster player | `participant_type`, `status`, `coach_id`, `walk_in_fee_sen`, `note`, `marked_by`, and `enrollment_id` (see below). |
| `assessment_scores` | 1 row per player × scored skill | The rubric pills; absent players / cleared pills write nothing. |
| `enrollments` | **never** | Only *linked to* — never created here. |
| `programs` / `sports` | never | Chosen from existing catalog. |

## `enrollment_id` on an attendance — a pointer, not a new row

It points at an enrolment that **already exists** (created at registration via People → Enrolments).

| Roster type | `enrollment_id` | Fee |
|---|---|---|
| **Enrolled** | their enrolment **in this timeslot** | — |
| **Make-up** | their live-credit enrolment **from another timeslot** (`Student::liveCreditEnrollment()`) | — |
| **Walk-in** | `null` | pays `walk_in_fee_sen` |

## Credits are *derived*, never stored

There is **no counter** to decrement. A credit is "used" simply because an attendance row exists
that points to the enrolment with a consuming status:

```
enrolment.creditsUsed() = count(attendances
    where enrollment_id = <enrolment>
    and status in [present, late, absent])       // 'excused' does NOT consume
```

So writing the attendance **is** the credit consumption — computed on read, not saved as a number.

## New (ad-hoc) session — create-on-Save

Expanding the "＋ Create new session" card (program + start/end time) only **stages** it in
memory. The `offerings` row is written **inside the Save transaction**, together with the
session — so a started-but-never-saved session leaves **nothing** behind (no orphan timeslot).
A soft **overlap warning** (banner + confirm) fires when the staged time collides with a
session already running that day — it warns, never blocks (a second team at the same hour is
legitimate).

## Deleting

Deleting a saved session removes its `attendances` (and their `assessment_scores` cascade). This
reverses credit consumption automatically, because credits are counted from attendances.

For an **ad-hoc (one-off) session**, its one-off timeslot is removed too once its last session is
gone — so deleting never leaves an orphan timeslot behind.
