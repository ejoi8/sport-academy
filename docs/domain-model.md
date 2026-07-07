# Domain model & core concepts

How registration, scheduling, and training delivery fit together. Read this before touching
enrolments, offerings, or Run Training — several rules here are deliberately non-obvious.

## The entities (WooCommerce analogy)

The booking side was designed to feel like WooCommerce products, monthly.

| Concept | Model | What it is |
|---|---|---|
| Product | **Program** | "Group Training" — holds the *defaults*: monthly price, walk-in fee, `default_sessions`. |
| Purchasable variation | **Offering** | One month's bookable slot: `Group Training · Wed 6pm · July · RM120 · 4 credits · cap 12`. One per program **per month**. Each month's offerings must exist before students can enrol into them — either created by hand or via the "Clone to month" bulk action on Catalog → Timeslots (schedule only, never touches enrolments; see [handover.md](handover.md) decisions log). |
| The order / purchase | **Enrolment** | Student X bought that offering (status + **price snapshot** + **credits snapshot**). |
| Fulfilment | **TrainingSession → Attendance** | A session ran on a date; the student showed up (consuming a credit) and was scored. |
| The rubric | **SkillCategory → Skill** | The skills a coach scores (Technical → Passing). |

## The flow, in one line

```
Program (product)
  └─ Offering    (July slot: schedule, capacity, session_count, price)   ← the reference / product
      └─ Enrolment   (student registers → SNAPSHOTS price + credits + status)   ← the "order"
          └─ TrainingSession   (created only when a coach runs training on a date)
              └─ Attendance    (consumes a credit; links to the enrolment)   ← delivery
                  └─ AssessmentScore   (1–5 per skill)
```

## The offering plays three roles

Everything hinges on the offering, so hold these three at once:

1. **The subject you enrol *into*** — an enrolment has `offering_id` pointing at it.
   **One offering → many enrolments** (one per enrolled student).
2. **The source of the snapshot** — at the moment of enrolment, the offering's `price_sen`
   and `session_count` are **copied** onto the enrolment (`price_sen`, `sessions_included`).
   After that they're independent: change the offering's price next month and existing
   enrolments keep what they paid. It's a **copy-at-enrolment, not a live link**.
3. **The anchor for delivery** — Run Training builds its roster from *that offering's*
   enrolments, and every `TrainingSession` belongs to an offering.

## Rules that surprise people

### `session_count` is credits, not a schedule
It's **how many sessions the fee covers** — a credit count snapshotted onto
`enrolment.sessions_included`. It is *decoupled* from the `weekday`, which is only a
scheduling **hint**. They usually both read "4" because one weekday ≈ 4 weeks/month, but
nothing links them — set it to 8 and the enrolment simply banks 8 credits.

### Session dates are never pre-generated — sessions are on-demand
There is **no stored list of "the 4 July dates."** Setting up an offering creates none;
booking creates none. A `training_sessions` row is born only when a coach opens Run Training,
picks a date, and **saves** (`TrainingSession::firstOrCreate(offering + date)`). The page's
"sessions on this day" list is **computed in memory** (`offeringsOnDate()` matches recurring
weekdays and one-off dates) — never persisted. This is the "date = planning only, assessment
on-demand" rule.

### The roster = the offering's subscribers (+ anyone added that day)
Expanding a session card loads the roster from the **offering's** enrolments
(`active`/`pending`/`overdue`) — everyone subscribed to that slot. It's keyed by **offering,
not date**, so the same subscribers appear on every session date in the month. Walk-ins /
make-ups added on a date are overlaid once that date's session is saved.

```
Roster on a date = subscribers (offering's enrolments, always shown)
                 + walk-ins / make-ups added on that date
```

### Credits are consumed by attendance, with a policy
- Consumed by **present / late / absent**; an **excused** absence does *not* (it can be made
  up). A no-show still burns the credit — they held the slot.
- `remaining = sessions_included − consumed`; credits **never expire** by default
  (`credits_expire_at` is null).
- A searched student is offered as a **make-up** (no fee, draws a live credit) only while they
  still hold one; otherwise they're a **paying walk-in**.

## Who may write what (data integrity)

The integrity-critical path — **attendance, scores, and credit *consumption*** — is written
**only by Run Training**. There is deliberately no Attendance or Score resource.

The Filament resources cover the **registration** side only: **Catalog** (Sport / Program /
Timeslot / Skill) and **People** (Student / Enrolment). Editing an enrolment via a resource
isn't "bypassing" Run Training — Run Training never created enrolments, it only *reads* them.
(Guardrails on risky enrolment fields — lock `sessions_included` after attendance, block
force-delete, audit trail — are planned but deferred while in development.)

## A worked example: Adam, July

1. **Setup (admin).** Program *Group Training* (`default_sessions = 4`, RM120/mo, RM40
   walk-in). For July, an offering: *Group Training · Wed 6pm · July · `session_count = 4` ·
   RM120 · cap 12 · Coach Farid*. → **An offering exists. No dates. No enrolments.**
2. **Booking.** Adam's parent enrols him. An enrolment is created: `status = active` (paid),
   `price_sen = 12000` (snapshot), `sessions_included = 4` (snapshot). → **Adam holds 4
   credits. Still no dates.**
3. **Wed 9 July.** Coach Farid opens Run Training (the date defaults to today) → the day's
   session list shows a *Group Training · 18:00* card → he expands it. The roster shows
   **Adam + the other enrolled kids**. Farid marks Adam present, scores him, **Saves**. → A
   **TrainingSession** (9 Jul) and **Attendance** (linked to his enrolment) now exist; Adam
   reads **`1 / 4`**.
4. **16, 23, 30 July.** Same slot, same subscribers, a new session each time → Adam reaches
   **`4 / 4`**.
5. **Edge — Adam misses 16 July, comes Saturday instead.** On the Saturday offering, Farid
   searches Adam; because he still has a live credit he's offered as a **make-up** (no fee)
   that consumes a Wednesday credit. Once all 4 are spent, the same search shows him as a
   **paying walk-in**.

## Declined: planned sessions

Pre-generated session schedules (planned sessions + multi-weekday offerings) were considered
and **declined** — they add ongoing management the academy doesn't want. Sessions stay
on-demand. The shipped mitigation is the unused-credits tracking (Enrolment filters + dashboard
widget). The full design record lives in
[design-planned-sessions.md](design-planned-sessions.md).
