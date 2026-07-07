<?php

namespace App\Providers;

use App\Listeners\ActivateEnrollmentOnPayment;
use App\Models\Enrollment;
use App\Observers\EnrollmentObserver;
use Ejoi\PaymentGateway\Laravel\Events\PaymentStatusChanged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // super_admin is the super-admin: full access to every gate/policy ("sees all", SPEC §2.7).
        // Mirrors Shield's super-admin mechanism, keyed to our seeded 'super_admin' role. Returning
        // null (not false) lets normal policy/permission checks proceed for everyone else.
        //
        // Exception: the delete family is deliberately NOT bypassed. It keeps deferring to the
        // policies so the history-preserving guardrails (Enrollment/Student/Offering
        // deletionBlockedReason) hold for EVERYONE, super_admins included — see the "block
        // history-destroying deletes" invariant in docs/handover.md.
        Gate::before(function ($user, string $ability): ?bool {
            if (! $user->hasRole('super_admin')) {
                return null;
            }

            return in_array($ability, ['delete', 'deleteAny', 'forceDelete', 'forceDeleteAny'], true)
                ? null
                : true;
        });

        Enrollment::observe(EnrollmentObserver::class);
        Event::listen(PaymentStatusChanged::class, ActivateEnrollmentOnPayment::class);
    }
}
