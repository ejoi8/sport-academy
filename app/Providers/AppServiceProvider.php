<?php

namespace App\Providers;

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
        // Admin is the super-admin: full access to every gate/policy ("Admin sees all", SPEC §2.7).
        // Mirrors Shield's super-admin mechanism, keyed to our named 'Admin' role. Returning null
        // (not false) for everyone else lets normal policy/permission checks proceed.
        Gate::before(fn ($user, string $ability) => $user->hasRole('Admin') ? true : null);
    }
}
