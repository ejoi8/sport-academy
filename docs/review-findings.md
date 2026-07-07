# Code review — findings & open decisions

A comprehensive read-through of the codebase (2026-07-07). The domain core (credits,
snapshots, on-demand sessions, the Run Training writer) and the booking/payment concurrency
are well-built and internally consistent — this note only records what needs attention. Two
items need a **decision** (they are not obvious one-liners); the rest are minor.

## Status — all items patched (2026-07-07)

Every finding below has now been addressed on this branch. Summary of what shipped and the
decisions taken:

| Item | Resolution | Commit |
|---|---|---|
| Carried credits | `carriedCreditsCount()` now honours `credits_expire_at` | `fix(credits): exclude expired credits…` |
| **D1** authorization | **Decision:** keep staff-wide access (permissive by design), but make the super-admin bypass real — keyed to `super_admin`, with the delete family excluded so the history guardrails still hold for everyone. | `fix(auth): make the super_admin gate real…` |
| **D2** `AcademySettings` | **Decision:** delete the orphaned scaffold (can return with the enum + migration when scheduled). | `chore(settings): remove orphaned AcademySettings…` |
| M1 activation race | Enrolment row locked (`lockForUpdate`) inside a transaction during activation. | `fix(payments): lock the enrolment row…` |
| M2 `saveCoach` | Asserts the actor holds a staff role before minting a coach login. | `fix(run-training): …guard coach creation` |
| M3 re-book message | Distinguishes a cancelled (soft-deleted) enrolment and points to staff. | `fix(booking): clearer message…` |
| M4 attended count | Session card counts only present + late as attended (regression test added). | `fix(run-training): count only present/late…` |

The detail below is kept as the record of *why* each change was made.

## Findings (all resolved above)

### D1 — Authorization: `Gate::before` is dead, and "fixing" it would break a delete guardrail

`app/Providers/AppServiceProvider.php` registers:

```php
Gate::before(fn ($user, string $ability) => $user->hasRole('Admin') ? true : null);
```

- No role named `Admin` exists — `RoleSeeder` seeds `admin`, `coach`, `parent`, `super_admin`
  (all lowercase). The one `'Admin'` literal in `DemoSeeder` is a *user's name*, not a role.
  So this gate **never fires**.
- Shield's own super-admin bypass is also inactive: the vendor default (no published
  `config/filament-shield.php`) has `super_admin.define_via_gate => false`, and
  `shield:generate` has not been run, so there are no generated permission policies.
- Five of eight resources (Program, Sport, Skill, SkillCategory, Payment) have **no policy**,
  so Filament defaults to *allow*. The three that do (Enrollment/Student/Offering) return
  `true` for everything except the delete guardrails.

**Effect today:** authorization is all-or-nothing at the panel door (`canAccessPanel` =
admin/coach/super_admin). Once inside, **any staff user — including a plain `coach` — can edit
program pricing, delete catalog rows, edit any enrolment, and approve / reject / record
payments.** Role-awareness exists only for dashboard *widget* visibility, not resource access.

**The trap:** simply correcting the role name (`'Admin'` → `'admin'` or `'super_admin'`) is
**not** safe. A live `Gate::before` returning `true` short-circuits *all* policy checks,
including `EnrollmentPolicy::delete` / `OfferingPolicy::delete` — so those users would then be
able to force-delete enrolments/offerings that have recorded attendance, destroying history and
breaking invariant "block history-destroying deletes" (see [handover.md](handover.md)). The gate
is currently harmless *only because* it is dead.

**Decision to make:**
1. Should there be an intra-panel distinction at all (e.g. coaches must not reach Finance /
   pricing), or is staff-wide access acceptable for now (it matches "permissive by design", and
   every seeded staff member is also `super_admin`)?
2. If a super-admin bypass is wanted, it must **exempt the deletion guardrails** — either keep
   the guardrails as an explicit `Gate::before` carve-out, or move them out of the policy
   `delete()` methods so a blanket bypass can't skip them. Do not enable the bypass without this.

Until decided, the dead gate is best left as-is (or removed entirely, since it does nothing) so
it stops reading as an active super-admin mechanism that it is not.

### D2 — `AcademySettings` is orphaned and references a missing enum

`app/Settings/AcademySettings.php` imports `App\Enums\PaymentMode`, which **does not exist**, and
there is no `database/settings/` migration to hydrate the `academy` settings group. The class is
referenced nowhere, so it is inert today — but resolving it from the container would fatal on the
missing enum.

Its fields (`head_coach_user_id`, `parent_top_performer_visible`, `payment_mode`,
`default_gateway`, `advanced_reports_enabled`, `free_first_month`) look like planned settings.

**Decision to make:** either
- **build it out** — add the `PaymentMode` enum, a settings migration, and wire the fields to the
  places that currently read config/hardcoded values; or
- **delete the file** — remove the dead scaffold until the feature is actually scheduled.

## Minor / cosmetic (no decision needed — fix opportunistically)

| # | Finding | Location |
|---|---|---|
| M1 | `BookingConfirmed` / activation can double-fire under a concurrent duplicate paid event (no row lock; two paid events could both read `Pending`). A `lockForUpdate()` or a unique activation marker closes it. | `app/Listeners/ActivateEnrollmentOnPayment.php` |
| M2 | `saveCoach()` lets any staff member mint `coach`-role users, ungated. | `app/Filament/Pages/RunTraining.php` (`saveCoach`) |
| M3 | Cancelled (soft-deleted) enrolment permanently blocks funnel re-booking into the same offering, with a misleading "already booked" message. Re-enrolment must be done admin-side. | `app/Livewire/PublicSite/BookingWizard.php` (`submit`) |
| M4 | Session-card "attended" count uses `withCount('attendances')`, so it includes absent/excused rows, not just present/late. | `app/Filament/Pages/RunTraining.php` (`sessionsOnDate`) |

## Test-coverage gaps (from the review)

- `tests/Unit` holds only the placeholder `ExampleTest` — all domain-logic tests run under
  `RefreshDatabase` in `Feature`.
- No model factories except `UserFactory` → heavy `Model::create` duplication across tests/seeders.
- The rich `SC-*` demo scenarios are set up but never asserted at the seeder level (only coarse
  counts in `SeederTest`).
- Webhook signature verification and cross-role resource authorization are untested.
