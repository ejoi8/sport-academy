<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed a usable baseline: roles, a coach login (coach@academy.test / password),
     * the Football rubric, and a small weekend catalog without students or enrollments.
     *
     * For richer, scenario-tagged data (multiple coaches, months of history and
     * scores) run the demo set instead:
     *   php artisan db:seed --class="Database\Seeders\DemoSeeder"
     */
    public function run(): void
    {
        $this->call(BaselineSeeder::class);
    }
}
