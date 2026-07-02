<?php

namespace Database\Seeders;

use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\Program;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\Sport;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RunTrainingPocSeeder extends Seeder
{
    public function run(): void
    {
        $coach = User::firstOrCreate(
            ['email' => 'coach@academy.test'],
            [
                'name' => 'Coach Farid',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        // Shield super admin: its Gate::before checks hasRole('super_admin') on every panel request.
        $coach->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
        // Coach role so the coach appears in the Run Training coach dropdown (which filters to coaches).
        $coach->assignRole(Role::firstOrCreate(['name' => 'coach', 'guard_name' => 'web']));

        $sport = Sport::create(['name' => 'Football']);

        // Rubric: categories -> skills (the columns the coach scores)
        $rubric = [
            'Technical' => ['Passing', 'Shooting', 'Dribbling', 'First touch'],
            'Physical' => ['Speed', 'Stamina'],
            'Mental' => ['Teamwork'],
        ];

        $sort = 0;
        foreach ($rubric as $categoryName => $skillNames) {
            $category = SkillCategory::create([
                'sport_id' => $sport->id,
                'name' => $categoryName,
                'sort_order' => $sort++,
            ]);

            foreach ($skillNames as $skillName) {
                Skill::create([
                    'sport_id' => $sport->id,
                    'skill_category_id' => $category->id,
                    'name' => $skillName,
                    'sort_order' => $sort++,
                ]);
            }
        }

        $group = Program::create([
            'sport_id' => $sport->id,
            'name' => 'Group Training',
            'base_price_sen' => 12000,
            'walk_in_fee_sen' => 4000,
        ]);

        $oneToOne = Program::create([
            'sport_id' => $sport->id,
            'name' => 'One-to-One',
            'base_price_sen' => 28000,
            'walk_in_fee_sen' => 8000,
        ]);

        $period = now()->format('Y-m');

        $groupOffering = Offering::create([
            'program_id' => $group->id,
            'period' => $period,
            'schedule_type' => 'recurring',
            'weekday' => 3, // Wednesday (ISO: Mon=1)
            'start_time' => '18:00',
            'end_time' => '19:30',
            'capacity' => 12,
            'price_sen' => 12000,
            'default_coach_id' => $coach->id,
            'is_open' => true,
        ]);

        Offering::create([
            'program_id' => $oneToOne->id,
            'period' => $period,
            'schedule_type' => 'recurring',
            'weekday' => 6, // Saturday
            'start_time' => '10:00',
            'end_time' => '11:00',
            'capacity' => 3,
            'price_sen' => 28000,
            'default_coach_id' => $coach->id,
            'is_open' => true,
        ]);

        $names = ['Adam Rahman', 'Mia Rahman', 'Yusuf Karim', 'Aisha Lim', 'Ethan Tan', 'Zara Ng', 'Daniel Wong', 'Sofia Abdullah'];

        foreach ($names as $index => $name) {
            $student = Student::create([
                'name' => $name,
                'ic_number' => '15'.str_pad((string) ($index + 1), 10, '0', STR_PAD_LEFT),
                'dob' => now()->subYears(10)->subMonths($index)->toDateString(),
                'gender' => $index % 2 === 0 ? 'male' : 'female',
                'is_active' => true,
            ]);

            // Enrol the first 6 into the Group offering: 4 active, 2 pending.
            if ($index < 6) {
                Enrollment::create([
                    'student_id' => $student->id,
                    'offering_id' => $groupOffering->id,
                    'status' => $index < 4 ? 'active' : 'pending',
                    'price_sen' => 12000,
                    'sessions_included' => 4,
                ]);
            }
        }
    }
}
