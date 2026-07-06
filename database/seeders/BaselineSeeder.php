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
 * (coach@academy.test / password), and a weekend catalog for Group Training,
 * One-to-One, and Goalkeeper. No students or enrollments are seeded.
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
            'name' => 'Group Training',
        ], [
            'base_price_sen' => 12000,
            'walk_in_fee_sen' => 4000,
            'default_sessions' => 4,
        ]);

        $oneToOne = Program::firstOrCreate([
            'sport_id' => $sport->id,
            'name' => 'One-to-One',
        ], [
            'base_price_sen' => 28000,
            'walk_in_fee_sen' => 8000,
            'default_sessions' => 4,
        ]);

        $goalkeeper = Program::firstOrCreate([
            'sport_id' => $sport->id,
            'name' => 'Goalkeeper',
        ], [
            'base_price_sen' => 15000,
            'walk_in_fee_sen' => 5000,
            'default_sessions' => 4,
        ]);

        $period = now()->format('Y-m');
        $slots = [
            ['weekday' => 6, 'start' => '09:00', 'end' => '10:30', 'label' => 'Saturday morning'],
            ['weekday' => 6, 'start' => '18:00', 'end' => '19:30', 'label' => 'Saturday evening'],
            ['weekday' => 7, 'start' => '09:00', 'end' => '10:30', 'label' => 'Sunday morning'],
            ['weekday' => 7, 'start' => '18:00', 'end' => '19:30', 'label' => 'Sunday evening'],
        ];

        $programs = [
            [
                'program' => $group,
                'capacity' => 12,
                'session_count' => 4,
                'price_sen' => 12000,
            ],
            [
                'program' => $oneToOne,
                'capacity' => 1,
                'session_count' => 4,
                'price_sen' => 28000,
            ],
            [
                'program' => $goalkeeper,
                'capacity' => 10,
                'session_count' => 4,
                'price_sen' => 15000,
            ],
        ];

        foreach ($programs as $programConfig) {
            foreach ($slots as $slot) {
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
