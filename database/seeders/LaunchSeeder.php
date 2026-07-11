<?php

namespace Database\Seeders;

use App\Models\Offering;
use App\Models\Program;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * A launch-ready academy with NO students yet — everything a real opening needs so parents can
 * start registering: roles, the rubric, an admin + the coaching team, the three programmes at
 * today's fees, and open weekend timeslots for the current AND next month.
 *
 * Idempotent (firstOrCreate throughout), so it's safe to run on a fresh or partially-set-up DB:
 *   php artisan db:seed --class="Database\Seeders\LaunchSeeder"
 *
 * Logins, all "password": admin@admin.com (super-admin — full panel) and coach@coach.com /
 * amir@ / lena@ / hafiz@academy.test (the `coach` role — the coach console, not the admin resources).
 */
class LaunchSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RoleSeeder::class, RubricSeeder::class]);

        $sport = Sport::where('name', 'Football')->firstOrFail();
        $password = Hash::make('password');

        // The admin runs the panel — super_admin is the app's full-access role (Gate::before).
        $this->staff('Admin', 'admin@admin.com', '012-000 0001', $password)->syncRoles(['super_admin']);

        // The coaching team — plain `coach` role: the coach console, not the admin resources.
        $sequence = 1;
        $coaches = [];

        foreach (['Farid' => 'coach@coach.com', 'Amir' => 'amir@academy.test', 'Lena' => 'lena@academy.test', 'Hafiz' => 'hafiz@academy.test'] as $name => $email) {
            $coach = $this->staff('Coach '.$name, $email, '012-000 100'.$sequence++, $password);
            $coach->syncRoles(['coach']);
            $coaches[$name] = $coach;
        }

        $group = Program::firstOrCreate(['sport_id' => $sport->id, 'name' => 'Group'], ['base_price_sen' => 16000, 'walk_in_fee_sen' => 5000, 'default_sessions' => 4]);
        $oneToOne = Program::firstOrCreate(['sport_id' => $sport->id, 'name' => '1-on-1'], ['base_price_sen' => 24000, 'walk_in_fee_sen' => 7500, 'default_sessions' => 4]);
        $goalkeeper = Program::firstOrCreate(['sport_id' => $sport->id, 'name' => 'Goalkeeper'], ['base_price_sen' => 12000, 'walk_in_fee_sen' => 3750, 'default_sessions' => 4]);

        // The real weekend timetable, each slot with its coach and today's fee (see the package table).
        $slots = [
            ['program' => $group, 'weekday' => 6, 'start' => '16:00', 'end' => '18:00', 'cap' => 40, 'price' => 16000, 'coach' => 'Amir'],
            ['program' => $oneToOne, 'weekday' => 6, 'start' => '16:00', 'end' => '18:00', 'cap' => 12, 'price' => 24000, 'coach' => 'Farid'],
            ['program' => $oneToOne, 'weekday' => 7, 'start' => '09:00', 'end' => '11:00', 'cap' => 12, 'price' => 24000, 'coach' => 'Lena'],
            ['program' => $goalkeeper, 'weekday' => 6, 'start' => '16:00', 'end' => '18:00', 'cap' => 12, 'price' => 12000, 'coach' => 'Hafiz'],
            ['program' => $goalkeeper, 'weekday' => 7, 'start' => '09:00', 'end' => '11:00', 'cap' => 12, 'price' => 12000, 'coach' => 'Hafiz'],
        ];

        // Open this month AND next, so parents can register for the upcoming month too.
        foreach ([now()->startOfMonth(), now()->startOfMonth()->addMonthNoOverflow()] as $monthStart) {
            $period = $monthStart->format('Y-m');

            foreach ($slots as $slot) {
                Offering::firstOrCreate([
                    'program_id' => $slot['program']->id,
                    'period' => $period,
                    'schedule_type' => 'recurring',
                    'weekday' => $slot['weekday'],
                    'start_time' => $slot['start'],
                    'end_time' => $slot['end'],
                ], [
                    'capacity' => $slot['cap'],
                    'session_count' => 4,
                    'price_sen' => $slot['price'],
                    'default_coach_id' => $coaches[$slot['coach']]->id,
                    'is_open' => true,
                ]);
            }
        }
    }

    private function staff(string $name, string $email, string $phone, string $password): User
    {
        return User::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'phone' => $phone, 'password' => $password, 'email_verified_at' => now()],
        );
    }
}
