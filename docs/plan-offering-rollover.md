# Execution plan — Offering rollover + current-period admin defaults

A self-contained build plan for an implementing agent (written to be executable by a cheap/less
capable model — every step is explicit and mechanical; judgment calls are pre-made).

> **Before writing any code, read:** [handover.md](handover.md) (stack, invariants, conventions),
> [domain-model.md](domain-model.md). Ground rules: money is integer `*_sen`; Filament v5
> **nested** resource layout (Schemas/ + Tables/ subfolders); run `php vendor/bin/pint --dirty`
> + full `php artisan test` after every slice and keep it green; commits are conventional style
> with **no AI attribution of any kind**.
> **Never run `git checkout -- .`, `git reset --hard`, or `git clean` — the working tree may
> carry other in-progress work. To revert your own changes, revert only the files you
> created/edited.**
> **READ every file you are about to edit, in full, before editing it.** Where this plan states
> a method/column/class name, verify it against the actual file first — if a name differs
> slightly, use the real one and note the correction in your final report.

## Why this exists

Offerings are created **per calendar month** (the `period` column, `YYYY-MM`). Nothing currently
creates next month's recurring offerings automatically — an admin must remember to do it, every
month, for every recurring class. If it's missed, a coach opens Run Training on the 1st and finds
no scheduled session that day; the "＋ Create new session" escape hatch still works, but it starts
with an empty roster (no auto-matched enrolled students), which defeats the point of having a
recurring class. Separately, the Catalog → Timeslots and People → Enrolments admin lists mix every
past month together by default, making it hard to see "what's actually running this month" as the
academy operates over years.

## Decisions locked — do not deviate from these

- **D1 — schedule only, never money.** This feature creates **only `Offering` rows** (the
  schedule shell: weekday/time/capacity/price/coach/is_open) for the next period. It **never**
  creates, renews, or modifies any `Enrollment` row, under any circumstance. Continuing a
  student into a new month **always** requires an explicit payment/registration action (existing
  manual admin entry, or the public booking funnel) — this was explicitly rejected as an
  auto-renewal/auto-billing concern by the product owner. If you find yourself writing
  `Enrollment::create(...)` anywhere in this feature, stop — that is out of scope.
- **D2 — recurring only.** One-off offerings (`schedule_type = one_off` — clinics, ad-hoc
  sessions) are **never** rolled forward; they are single-date events by definition. The
  rollover's source query must filter `schedule_type = recurring`.
- **D3 — active programs only.** Only offerings whose `program.is_active = true` are eligible
  sources. A retired/inactive program's recurring class must not be silently resurrected into
  next month.
- **D4 — idempotent, app-level only.** Running the rollover twice for the same month pair must
  create zero duplicates. The matching key is `(program_id, target period, schedule_type =
  recurring, weekday, start_time)`. **Do not add a database unique constraint on this tuple** —
  the app deliberately allows two independently-created offerings at the same weekday/time (the
  "second team" / overlapping-session scenario already exists in `DemoSeeder`). The duplicate
  check is an application-level guard inside this feature only, not a general uniqueness rule.
- **D5 — manual button, not a scheduler.** The action is triggered by an admin clicking a
  button. Do **not** add a scheduled command/cron job for this — the owner explicitly asked for
  "the button," not automation of the trigger itself.

---

# Phase 1 — "Roll forward to next month" header action

**File:** `app/Filament/Resources/Offerings/Pages/ListOfferings.php`

Read the file fully first. It currently has a `getHeaderActions()` (or similar) returning at
least a `CreateAction`. Add a second header action alongside it — do not remove or reorder the
existing one(s).

### The action

```php
use App\Enums\ScheduleType;
use App\Models\Offering;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// inside getHeaderActions():
Action::make('rollForward')
    ->label('Roll forward to next month')
    ->icon(Heroicon::ArrowPath) // or any icon already used elsewhere in this resource for a
                                // "repeat/refresh" concept — pick one consistent with existing
                                // action icons; verify the exact Heroicon case exists in this
                                // app's Filament/Heroicon version before using it.
    ->requiresConfirmation()
    ->modalHeading('Roll forward to next month')
    ->modalDescription(fn (): string => $this->rolloverPreviewText())
    ->modalSubmitActionLabel('Roll forward')
    ->action(fn () => $this->performRollover()),
```

### Supporting methods (add as `protected` methods on the same page class)

```php
/**
 * Recurring offerings for an active program in the current period that don't already have a
 * matching offering (same program/weekday/start time) in next period.
 *
 * @return array{fromPeriod: string, toPeriod: string, toCreate: \Illuminate\Support\Collection, toSkip: \Illuminate\Support\Collection}
 */
protected function rolloverPlan(): array
{
    $fromPeriod = now()->format('Y-m');
    $toPeriod = now()->addMonthNoOverflow()->format('Y-m');

    $sources = Offering::query()
        ->where('period', $fromPeriod)
        ->where('schedule_type', ScheduleType::Recurring->value)
        ->whereHas('program', fn ($query) => $query->where('is_active', true))
        ->with('program')
        ->get();

    $toCreate = collect();
    $toSkip = collect();

    foreach ($sources as $source) {
        $exists = Offering::query()
            ->where('program_id', $source->program_id)
            ->where('period', $toPeriod)
            ->where('schedule_type', ScheduleType::Recurring->value)
            ->where('weekday', $source->weekday)
            ->where('start_time', $source->start_time)
            ->exists();

        ($exists ? $toSkip : $toCreate)->push($source);
    }

    return compact('fromPeriod', 'toPeriod', 'toCreate', 'toSkip');
}

protected function rolloverPreviewText(): string
{
    $plan = $this->rolloverPlan();
    $fromLabel = Carbon::parse($plan['fromPeriod'].'-01')->format('M Y');
    $toLabel = Carbon::parse($plan['toPeriod'].'-01')->format('M Y');

    if ($plan['toCreate']->isEmpty()) {
        return "No open recurring timeslots from {$fromLabel} need rolling forward to {$toLabel} — {$plan['toSkip']->count()} already exist.";
    }

    $labels = $plan['toCreate']->map(fn (Offering $o): string => $o->label())->implode(', ');

    return "This will create {$plan['toCreate']->count()} new timeslot(s) for {$toLabel}, cloned from {$fromLabel}: {$labels}."
        .($plan['toSkip']->isNotEmpty() ? " {$plan['toSkip']->count()} already exist and will be skipped." : '');
}

protected function performRollover(): void
{
    $plan = $this->rolloverPlan();

    DB::transaction(function () use ($plan): void {
        foreach ($plan['toCreate'] as $source) {
            Offering::create([
                'program_id' => $source->program_id,
                'period' => $plan['toPeriod'],
                'schedule_type' => ScheduleType::Recurring->value,
                'weekday' => $source->weekday,
                'start_time' => $source->start_time,
                'end_time' => $source->end_time,
                'capacity' => $source->capacity,
                'session_count' => $source->session_count,
                'price_sen' => $source->price_sen,
                'default_coach_id' => $source->default_coach_id,
                'is_open' => $source->is_open,
            ]);
        }
    });

    Notification::make()
        ->success()
        ->title('Rolled forward to '.Carbon::parse($plan['toPeriod'].'-01')->format('M Y'))
        ->body("{$plan['toCreate']->count()} new timeslot(s) created, {$plan['toSkip']->count()} already existed.")
        ->send();
}
```

Verify exact column/method names against `app/Models/Offering.php` (does it have a `label()`
method already? it's referenced elsewhere in this codebase — reuse it) and
`app/Enums/ScheduleType.php` before finalizing. If a permission policy already gates the
`CreateAction` on this page (check for `->authorize(...)` or Filament's default policy-based
visibility, and check whether `app/Policies/OfferingPolicy.php` exists), mirror the **same**
gating mechanism on this new action for consistency — do not invent a different authorization
approach.

---

# Phase 2 — default admin table filters to the current period

**Files:** `app/Filament/Resources/Offerings/Tables/OfferingsTable.php` and
`app/Filament/Resources/Enrollments/Tables/EnrollmentsTable.php`. Read both fully first.

- **Enrollments table** already has a Month/period filter (documented in
  `docs/design-planned-sessions.md` as part of the shipped "unfinished-slot" work). Locate it and
  add/confirm `->default(now()->format('Y-m'))` on the `SelectFilter`.
- **Offerings table**: check whether a Month/period `SelectFilter` already exists. `OfferingResource::monthOptions()` is a static helper already used to populate the period `Select`
  in the create/edit form — if a table filter already reuses it, just add
  `->default(now()->format('Y-m'))`. If no such filter exists on the table yet, add a minimal one:
  ```php
  SelectFilter::make('period')
      ->label('Month')
      ->options(OfferingResource::monthOptions())
      ->default(now()->format('Y-m')),
  ```
  matching whatever import/style conventions the rest of the file already uses.

**Side-effect warning:** adding a default filter value can hide rows that existing tests currently
expect to see without applying any filter. After this change, run the full suite and fix any
test that now needs to explicitly set or clear the period filter (e.g. via whatever
`->filterTable(...)` helper pattern this codebase's existing Filament table tests already use —
grep for `filterTable` in `tests/Feature/CatalogTest.php` and `PeopleTest.php` for the pattern).

---

## Tests

Add to `tests/Feature/CatalogTest.php` (follow its existing style —
`Livewire::test(ListOfferings::class)`, `Filament::setCurrentPanel(...)`, an authenticated
super_admin, as done elsewhere in that file). Use `now()->format('Y-m')` /
`Carbon::parse(...)->addMonthNoOverflow()` for period math — do not hardcode calendar dates
(matches the convention already used throughout `DemoSeeder` and `RunTrainingTest`).

1. **Creates the expected next-period offerings** — seed one open recurring offering for an
   active program this period; call the rollover action; assert an `Offering` row now exists for
   next period with the same program/weekday/start_time/capacity/price/coach.
2. **One-off offerings are never rolled forward** — seed a one-off offering this period; run
   rollover; assert no matching one-off appears next period (and the recurring-only source query
   never touched it).
3. **Inactive programs are skipped** — a program with `is_active = false` and a recurring
   offering this period; run rollover; assert nothing is created for it next period.
4. **Idempotent** — run the action twice; assert the second run creates zero new rows and the
   skip count reflects what already exists.
5. **Distinct times aren't blocked by an existing different-time offering** — a next-period
   offering already exists for the same program at a *different* weekday/time; running rollover
   still creates the *this*-time offering (the match key includes weekday+start_time, not just
   program+period).
6. **Critical regression guard — zero enrolments created.** Assert `Enrollment::count()` is
   identical before and after calling the rollover action. This is the test that proves D1 is
   respected; do not skip it.
7. **Filter defaults** — `Livewire::test(ListOfferings::class)` and
   `Livewire::test(ListEnrollments::class)` (verify the exact page class name) render with the
   period filter defaulted to the current month (assert via whatever pattern this codebase's
   existing Filament table-filter tests use for asserting a filter's initial state).
8. Sweep existing tests in `CatalogTest.php` / `PeopleTest.php` for any that list offerings or
   enrolments across multiple periods without setting a filter, and update them to explicitly set
   the period filter they need — per the Phase 2 side-effect warning above.

---

## Docs

- `docs/handover.md` — add a row to the **Decisions log** table:
  `| Monthly offering rollover | Manual button (2026-07-06) — clones open recurring offerings one
  month forward; never creates or renews Enrollment rows (renewal always requires payment, no
  auto-billing). See plan-offering-rollover.md |`
  Search the file for any existing language implying offerings must always be created by hand
  each month and reconcile it with the new button.
- `docs/domain-model.md` — if it states offerings are always manually created per period, add a
  short pointer to the rollover action.
- `README.md` — add this plan to the `## Project docs` list, matching the existing entries'
  format, e.g.:
  `- [Execution plan — Offering rollover](docs/plan-offering-rollover.md) — a manual "roll
  forward" button for next month's recurring timeslots, and current-period admin defaults.`

---

## Explicitly out of scope — do not build these

- **Any automatic or bulk Enrollment renewal/creation, in any form.** This was explicitly
  rejected: continuing a student into a new period always requires an explicit payment action.
  Do not add a "renew all active students" button, a renewal notification, a renewal reminder, or
  anything that writes to `enrollments` from this feature.
- A scheduled/cron-automatic rollover (D5) — button only.
- A database-level unique constraint on the offering-matching tuple (D4) — would break the
  deliberate overlapping "second team" scenario.
- Any change to Run Training, Quick Record (separate plan doc), or the public booking funnel.
- Auto-archiving or auto-closing old periods — only the *default view* changes; historical data
  and its full visibility (one filter-change away) are untouched.

## Acceptance checklist

- [ ] Clicking "Roll forward to next month" once creates the expected next-period `Offering`
      rows for every active program's open recurring current-period offerings, and none for
      one-off or inactive-program offerings.
- [ ] Running it again immediately creates nothing new and reports the correct skip count.
- [ ] `Enrollment::count()` is provably unchanged by the action (test + a manual click-through).
- [ ] Catalog → Timeslots and People → Enrolments open, by default, scoped to the current month;
      older periods are one filter change away.
- [ ] Full test suite green; `pint --dirty` clean; nothing committed unless explicitly asked.
