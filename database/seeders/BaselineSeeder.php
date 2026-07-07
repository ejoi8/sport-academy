<?php

namespace Database\Seeders;

use App\Models\Offering;
use App\Models\Program;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * A lean, deterministic baseline: roles, the Football rubric, a coach login
 * (coach@academy.test / password), and a weekend catalog for the academy's two real programs —
 * Group and 1-on-1 — on their weekend slots (Sabtu petang / Ahad pagi). No students or
 * enrollments are seeded.
 */
class BaselineSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            RubricSeeder::class,
        ]);

        $sport = Sport::where('name', 'Football')->firstOrFail();

        $coach = User::firstOrCreate(
            ['email' => 'coach@academy.test'],
            [
                'name' => 'Coach Farid',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );
        // super_admin: Gate::before grants the panel on every request.
        // coach: so the user appears in the Run Training coach dropdown (which filters to coaches).
        $coach->assignRole('super_admin');
        $coach->assignRole('coach');

        $group = Program::firstOrCreate([
            'sport_id' => $sport->id,
            'name' => 'Group',
        ], [
            'base_price_sen' => 12000,
            'walk_in_fee_sen' => 3750,
            'default_sessions' => 4,
        ]);

        $oneToOne = Program::firstOrCreate([
            'sport_id' => $sport->id,
            'name' => '1-on-1',
        ], [
            'base_price_sen' => 30000,
            'walk_in_fee_sen' => 9375,
            'default_sessions' => 4,
        ]);

        $period = now()->format('Y-m');

        // The real weekend timetable: Group on Sabtu petang; 1-on-1 on both Sabtu petang and
        // Ahad pagi. Each program's slots are its own — Group runs Saturday afternoon only.
        $programs = [
            [
                'program' => $group,
                'capacity' => 40,
                'session_count' => 4,
                'price_sen' => 12000,
                'slots' => [
                    ['weekday' => 6, 'start' => '16:00', 'end' => '18:00', 'label' => 'Sabtu petang'],
                ],
            ],
            [
                'program' => $oneToOne,
                'capacity' => 12,
                'session_count' => 4,
                'price_sen' => 30000,
                'slots' => [
                    ['weekday' => 6, 'start' => '16:00', 'end' => '18:00', 'label' => 'Sabtu petang'],
                    ['weekday' => 7, 'start' => '09:00', 'end' => '11:00', 'label' => 'Ahad pagi'],
                ],
            ],
        ];

        foreach ($programs as $programConfig) {
            foreach ($programConfig['slots'] as $slot) {
                Offering::firstOrCreate([
                    'program_id' => $programConfig['program']->id,
                    'period' => $period,
                    'schedule_type' => 'recurring',
                    'weekday' => $slot['weekday'],
                    'start_time' => $slot['start'],
                    'end_time' => $slot['end'],
                ], [
                    'capacity' => $programConfig['capacity'],
                    'session_count' => $programConfig['session_count'],
                    'price_sen' => $programConfig['price_sen'],
                    'default_coach_id' => $coach->id,
                    'is_open' => true,
                ]);
            }
        }
    }
}
