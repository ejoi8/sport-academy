<?php

namespace Database\Seeders;

use App\Models\Offering;
use App\Models\Program;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * A lean, deterministic baseline: roles, the Football rubric, an admin + a coach login
 * (admin@admin.com super-admin, coach@coach.com coach — both "password"), and the real weekend
 * catalog for the academy's three programs — Group, 1-on-1 and Goalkeeper — on their weekend slots
 * (Sabtu petang / Ahad pagi). No students or enrollments are seeded.
 *
 * Monthly fees + capacities mirror the published package table: Group RM160 / 40 slots (Sat only),
 * 1-on-1 RM240 / 12 per slot (Sat + Sun), Goalkeeper RM120 / 12 per slot (Sat + Sun); all 4
 * sessions a month. Walk-in fees aren't in that table — they stay at 1.25x the per-session rate.
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

        // The super-admin — full panel access (Gate::before grants every gate).
        $admin = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Admin',
                'phone' => '012-000 0001', // gateways refuse a checkout without a customer phone
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );
        $admin->assignRole('super_admin');

        // The main coach — the `coach` role so they appear in the Run Training coach dropdown
        // (which filters to coaches) and get the coach console, not the admin resources.
        $coach = User::firstOrCreate(
            ['email' => 'coach@coach.com'],
            [
                'name' => 'Coach Farid',
                'phone' => '012-000 1001',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );
        $coach->assignRole('coach');

        $group = Program::firstOrCreate([
            'sport_id' => $sport->id,
            'name' => 'Group',
        ], [
            'base_price_sen' => 16000,   // RM160 / month
            'walk_in_fee_sen' => 5000,   // RM50 (1.25x the RM40 per-session rate)
            'default_sessions' => 4,
        ]);

        $oneToOne = Program::firstOrCreate([
            'sport_id' => $sport->id,
            'name' => '1-on-1',
        ], [
            'base_price_sen' => 24000,   // RM240 / month
            'walk_in_fee_sen' => 7500,   // RM75 (1.25x the RM60 per-session rate)
            'default_sessions' => 4,
        ]);

        $goalkeeper = Program::firstOrCreate([
            'sport_id' => $sport->id,
            'name' => 'Goalkeeper',
        ], [
            'base_price_sen' => 12000,   // RM120 / month
            'walk_in_fee_sen' => 3750,   // RM37.50 (1.25x the RM30 per-session rate)
            'default_sessions' => 4,
        ]);

        $period = now()->format('Y-m');

        // The real weekend timetable: Group on Sabtu petang only; 1-on-1 and Goalkeeper on both
        // Sabtu petang and Ahad pagi. Each program's slots (and capacities) are its own.
        $programs = [
            [
                'program' => $group,
                'capacity' => 40,
                'session_count' => 4,
                'price_sen' => 16000,
                'slots' => [
                    ['weekday' => 6, 'start' => '16:00', 'end' => '18:00', 'label' => 'Sabtu petang'],
                ],
            ],
            [
                'program' => $oneToOne,
                'capacity' => 12,
                'session_count' => 4,
                'price_sen' => 24000,
                'slots' => [
                    ['weekday' => 6, 'start' => '16:00', 'end' => '18:00', 'label' => 'Sabtu petang'],
                    ['weekday' => 7, 'start' => '09:00', 'end' => '11:00', 'label' => 'Ahad pagi'],
                ],
            ],
            [
                'program' => $goalkeeper,
                'capacity' => 12,
                'session_count' => 4,
                'price_sen' => 12000,
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
