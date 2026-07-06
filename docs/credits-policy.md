# Session credits — policy (SOP/TNC source)

This is the academy's plain-English policy for how session credits work. The Run
Training screen implements these rules automatically — coaches never calculate
credits by hand.

## The five rules

1. **Each month's registration buys N sessions** ("credits" — usually 4).
2. **Attending your own weekly class uses this month's credit.** Present, late,
   and absent all use one — the spot was held either way. Excused does not.
3. **Unused credits never disappear** — unused sessions from previous months
   become "carried" credits.
4. **Carried credits are spent by joining an EXTRA session in the SAME
   program** (any day, same class/program): the player joins free as a
   make-up, oldest credits first. Carried credits are spendable in the same
   program only, and only up to the session's own month — a future month's
   prepaid credits never fund a make-up today. With no matching-program
   credits left, they pay the walk-in fee instead — and the coach can always
   charge the walk-in fee instead, even when credits exist.
5. **Regular monthly sessions never touch carried credits** — this month's fee
   covers this month's classes.

## Worked example (Adam)

- **June:** pays for 4, attends 2 → 2 credits carried.
- **July:** pays for 4 again. Every Saturday class uses a July credit
  (1/4, 2/4 …) — June leftovers are untouched.
- One Wednesday he joins another Group Training slot as an extra → make-up:
  free, uses 1 June credit → now +1 carried. (Had it been a Goalkeeper
  session instead, he'd have paid the walk-in fee — his carried credits are
  Group Training credits, not Goalkeeper ones.)
- After 4 Saturdays his badge reads **"4/4 · paid up"**. A 5th regular
  session shows **"+1 over"** — it's allowed, but it's time to renew.

## Badge cheat-sheet

| Badge | Meaning |
|---|---|
| `2/4` | In progress — 2 of 4 paid sessions used. |
| `4/4 · paid up` (amber) | All paid sessions used — renewal due soon. |
| `5/4 · +1 over` (red) | Over-delivered — never blocked, just flagged for renewal. |
| `+2 carried` | Unused past sessions of this program, usable as free make-ups. |
| `make-up` | Extra session, paid for by a carried credit. |
| `walk-in · RM40` | Extra session with no credits available — pays the fee. |

## Decisions on record

- **Credits are same-program only.** They belong to the program they were
  bought for — a make-up may only draw on same-program credits (e.g. a
  leftover Group Training credit cannot pay for a Goalkeeper make-up: the
  value doesn't match and it would cannibalise that program's own quota).
  Joining a different program's session is a walk-in fee (or a proper
  enrolment) instead. Goodwill cross-program exceptions are handled offline
  as a manual arrangement, never automated. The coach can always charge the
  walk-in fee instead, even when same-program credits exist.
- **Credits never expire by default.** `credits_expire_at` exists per
  enrolment for a future strict deal, but is not used today.
- **Over-delivery is never blocked**, only flagged — the coach can always
  keep running the session.
- **Renewal deals** (e.g. a discount because sessions are owed) are decided by
  the admin at re-enrolment and entered manually. The system does not
  automate pricing.
