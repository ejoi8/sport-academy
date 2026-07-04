<?php

namespace Database\Seeders;

use App\Models\AssessmentScore;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\Program;
use App\Models\Skill;
use App\Models\Sport;
use App\Models\Student;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Demo data:
 *  - Three recurring weekly programs (1-on-1, Group, Goalkeeper), ~4 sessions a month each.
 *  - One Football Clinic one-off (the month's 2nd Saturday afternoon).
 *  - 100 students per program, each attached to a parent account.
 *  - An admin login: admin@admin.com / password.
 *
 * Last month's occurrences are recorded (attendance + rubric scores); this month's are recorded
 * up to today, leaving upcoming dates empty so the "not recorded yet" state shows.
 */
class DemoSeeder extends Seeder
{
    private const COHORT_SIZE = 100;

    private const FAMILY_SIZE = 4;

    /** @var Collection<int, Skill> */
    private Collection $skills;

    /** @var array<string, User> */
    private array $coaches = [];

    /** @var array<string, Offering> */
    private array $offerings = [];

    /** @var array<string, array<string, mixed>> */
    private array $slots = [];

    private int $icSeq = 0;

    private int $parentSeq = 0;

    /** @var array<int, int> */
    private array $parentIds = [];

    // Every demo login is "password"; hash it once (bcrypt is slow) and reuse the digest.
    private string $password = '';

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            RubricSeeder::class,
        ]);

        $sport = Sport::where('name', 'Football')->firstOrFail();
        $this->skills = Skill::query()->orderBy('sort_order')->get();
        $this->password = Hash::make('password');

        $this->createAdmin();
        $this->createCoaches();
        $programs = $this->createPrograms($sport);

        $currentMonth = now()->startOfMonth();
        $historyMonth = now()->startOfMonth()->subMonthNoOverflow();
        $currentPeriod = $currentMonth->format('Y-m');
        $historyPeriod = $historyMonth->format('Y-m');

        // Three recurring weekly programs.
        $this->slots = [
            'one-to-one' => ['program' => '1-on-1', 'weekday' => 3, 'start' => '18:00', 'end' => '19:00', 'cap' => 120, 'price' => 28000, 'coach' => 'Farid', 'label' => '1-on-1'],
            'group' => ['program' => 'Group', 'weekday' => 6, 'start' => '09:00', 'end' => '10:30', 'cap' => 120, 'price' => 12000, 'coach' => 'Amir', 'label' => 'Group'],
            'goalkeeper' => ['program' => 'Goalkeeper', 'weekday' => 7, 'start' => '09:00', 'end' => '10:00', 'cap' => 120, 'price' => 15000, 'coach' => 'Lena', 'label' => 'Goalkeeper'],
        ];

        foreach ($this->slots as $code => $cfg) {
            $this->offerings["$historyPeriod|$code"] = $this->makeOffering($programs, $cfg, $historyPeriod);
            $this->offerings["$currentPeriod|$code"] = $this->makeOffering($programs, $cfg, $currentPeriod);
            $this->enrolFamilies(
                $this->offerings["$currentPeriod|$code"],
                $this->offerings["$historyPeriod|$code"],
                $cfg['label'],
            );
        }

        // Football Clinic one-off — the month's 2nd Saturday afternoon.
        $secondSaturday = $currentMonth->copy();
        while ($secondSaturday->dayOfWeekIso !== 6) {
            $secondSaturday->addDay();
        }
        $secondSaturday->addWeek();

        $clinic = Offering::create([
            'program_id' => $programs['Football Clinic']->id,
            'period' => $currentPeriod,
            'schedule_type' => 'one_off',
            'specific_date' => $secondSaturday->toDateString(),
            'start_time' => '14:00',
            'end_time' => '17:00',
            'capacity' => 120,
            'session_count' => 1,
            'price_sen' => 9000,
            'default_coach_id' => $this->coaches['Farid']->id,
            'is_open' => true,
        ]);
        $this->offerings["$currentPeriod|clinic"] = $clinic;
        $this->enrolFamilies($clinic, null, 'Clinic');

        $this->assignParentRole();

        $this->generateHistory($historyMonth, $historyPeriod);
        $this->generateCurrentToDate($currentMonth, $currentPeriod);
    }

    private function createAdmin(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            ['name' => 'Admin', 'password' => $this->password, 'email_verified_at' => now()],
        );
        $admin->assignRole('super_admin');
    }

    private function createCoaches(): void
    {
        foreach (['Farid' => 'coach@academy.test', 'Amir' => 'amir@academy.test', 'Lena' => 'lena@academy.test'] as $name => $email) {
            $coach = User::firstOrCreate(
                ['email' => $email],
                ['name' => 'Coach '.$name, 'password' => $this->password, 'email_verified_at' => now()],
            );
            $coach->assignRole('super_admin');
            $coach->assignRole('coach');
            $this->coaches[$name] = $coach;
        }
    }

    /**
     * @return array<string, Program>
     */
    private function createPrograms(Sport $sport): array
    {
        return [
            '1-on-1' => Program::create(['sport_id' => $sport->id, 'name' => '1-on-1', 'base_price_sen' => 28000, 'walk_in_fee_sen' => 8000, 'default_sessions' => 4]),
            'Group' => Program::create(['sport_id' => $sport->id, 'name' => 'Group Training', 'base_price_sen' => 12000, 'walk_in_fee_sen' => 4000, 'default_sessions' => 4]),
            'Goalkeeper' => Program::create(['sport_id' => $sport->id, 'name' => 'Goalkeeper', 'base_price_sen' => 15000, 'walk_in_fee_sen' => 5000, 'default_sessions' => 4]),
            'Football Clinic' => Program::create(['sport_id' => $sport->id, 'name' => 'Football Clinic', 'base_price_sen' => 9000, 'walk_in_fee_sen' => 3000, 'default_sessions' => 1]),
        ];
    }

    /**
     * @param  array<string, Program>  $programs
     * @param  array<string, mixed>  $cfg
     */
    private function makeOffering(array $programs, array $cfg, string $period): Offering
    {
        return Offering::create([
            'program_id' => $programs[$cfg['program']]->id,
            'period' => $period,
            'schedule_type' => 'recurring',
            'weekday' => $cfg['weekday'],
            'start_time' => $cfg['start'],
            'end_time' => $cfg['end'],
            'capacity' => $cfg['cap'],
            'session_count' => $programs[$cfg['program']]->default_sessions,
            'price_sen' => $cfg['price'],
            'default_coach_id' => $this->coaches[$cfg['coach']]->id,
            'is_open' => true,
        ]);
    }

    /**
     * Enrol 100 students (in families of four, each under its own parent) into a session — and into
     * last month's offering too, when given, so history records.
     */
    private function enrolFamilies(Offering $current, ?Offering $history, string $label): void
    {
        $child = 0;

        for ($f = 1; $f <= (int) (self::COHORT_SIZE / self::FAMILY_SIZE); $f++) {
            $parent = $this->makeParent($label.' family '.$f);

            for ($c = 1; $c <= self::FAMILY_SIZE; $c++) {
                $child++;
                $student = $this->makeStudent($parent->id, $label.' '.str_pad((string) $child, 3, '0', STR_PAD_LEFT));

                Enrollment::create([
                    'student_id' => $student->id,
                    'offering_id' => $current->id,
                    'status' => $this->cohortStatus($child),
                    'price_sen' => $current->price_sen,
                    'sessions_included' => $current->session_count,
                ]);

                if ($history) {
                    Enrollment::create([
                        'student_id' => $student->id,
                        'offering_id' => $history->id,
                        'status' => 'active',
                        'price_sen' => $history->price_sen,
                        'sessions_included' => $history->session_count,
                    ]);
                }
            }
        }
    }

    /** A spread of payment statuses so the roster's badges vary. */
    private function cohortStatus(int $i): string
    {
        return match (true) {
            $i % 10 === 0 => 'overdue',
            $i % 5 === 0 => 'pending',
            default => 'active',
        };
    }

    private function generateHistory(Carbon $historyMonth, string $historyPeriod): void
    {
        foreach ($this->slots as $code => $cfg) {
            $offering = $this->offerings["$historyPeriod|$code"] ?? null;
            if (! $offering) {
                continue;
            }

            $enrolled = $this->enrolledStudents($offering);
            if ($enrolled->isEmpty()) {
                continue;
            }

            // Two past sessions is enough history for trends without a heavy seed.
            foreach ($this->weekdayDates($historyMonth, $cfg['weekday'], 2) as $sessionIndex => $date) {
                $this->recordSession($offering, $date, $sessionIndex, $enrolled);
            }
        }
    }

    /**
     * Record this month's sessions that have already happened (up to today), leaving upcoming dates
     * empty so a coach sees recorded past sessions and the "not recorded yet" state side by side.
     */
    private function generateCurrentToDate(Carbon $currentMonth, string $currentPeriod): void
    {
        $today = today();

        foreach ($this->offerings as $key => $offering) {
            if (! str_starts_with($key, $currentPeriod.'|')) {
                continue;
            }

            $enrolled = $this->enrolledStudents($offering);
            if ($enrolled->isEmpty()) {
                continue;
            }

            $dates = $offering->specific_date
                ? collect([$offering->specific_date->copy()])
                : $this->weekdayDates($currentMonth, (int) $offering->weekday);

            foreach ($dates as $sessionIndex => $date) {
                if ($date->gt($today)) {
                    continue; // leave upcoming dates empty
                }

                $this->recordSession($offering, $date, $sessionIndex, $enrolled);
            }
        }
    }

    /**
     * @return Collection<int, Enrollment>
     */
    private function enrolledStudents(Offering $offering): Collection
    {
        return Enrollment::query()
            ->where('offering_id', $offering->id)
            ->whereIn('status', ['active', 'pending', 'overdue'])
            ->with('student')
            ->get();
    }

    /**
     * @param  Collection<int, Enrollment>  $enrolled
     */
    private function recordSession(Offering $offering, Carbon $date, int $sessionIndex, Collection $enrolled): void
    {
        $session = TrainingSession::create([
            'offering_id' => $offering->id,
            'session_date' => $date->toDateString(),
            'coach_id' => $offering->default_coach_id,
            'created_by' => $this->coaches['Farid']->id,
        ]);

        foreach ($enrolled->values() as $ei => $enrollment) {
            $this->recordAttendance($session, $enrollment, $sessionIndex, $ei);
        }
    }

    private function recordAttendance(TrainingSession $session, Enrollment $enrollment, int $sessionIndex, int $ei): void
    {
        $absent = $ei % 8 === 0 && $sessionIndex === 1;
        $status = $absent ? 'absent' : (($ei % 6 === 0 && $sessionIndex === 1) ? 'late' : 'present');

        $attendance = Attendance::create([
            'training_session_id' => $session->id,
            'student_id' => $enrollment->student_id,
            'enrollment_id' => $enrollment->id,
            'participant_type' => 'enrolled',
            'status' => $status,
            'coach_id' => $this->coachForIndex($ei),
            'walk_in_fee_sen' => null,
            'marked_by' => $this->coaches['Farid']->id,
        ]);

        if ($absent) {
            return;
        }

        // Scores climb across the month. Bulk-inserted for speed.
        $now = now();
        AssessmentScore::insert(
            $this->skills->map(fn (Skill $skill): array => [
                'attendance_id' => $attendance->id,
                'skill_id' => $skill->id,
                'score' => min(5, max(1, 2 + $sessionIndex + (($ei + $skill->id) % 2))),
                'created_at' => $now,
                'updated_at' => $now,
            ])->all(),
        );
    }

    private function makeParent(string $name): User
    {
        $parent = User::create([
            'name' => 'Parent '.$name,
            'email' => 'parent'.(++$this->parentSeq).'@demo.test',
            'password' => $this->password,
            'email_verified_at' => now(),
        ]);

        // Role is bulk-assigned later (see assignParentRole) — calling assignRole per parent churns
        // Spatie's permission cache and is dramatically slower at this scale.
        $this->parentIds[] = $parent->id;

        return $parent;
    }

    /** Assign the 'parent' role to every seeded parent in one insert, bypassing per-call cache churn. */
    private function assignParentRole(): void
    {
        $roleId = Role::where('name', 'parent')->value('id');
        $morph = (new User)->getMorphClass();

        $rows = array_map(fn (int $id): array => [
            'role_id' => $roleId,
            'model_type' => $morph,
            'model_id' => $id,
        ], $this->parentIds);

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('model_has_roles')->insert($chunk);
        }
    }

    private function makeStudent(int $parentId, string $name): Student
    {
        return Student::create([
            'parent_id' => $parentId,
            'name' => $name,
            'ic_number' => '07'.str_pad((string) (++$this->icSeq), 10, '0', STR_PAD_LEFT),
            'dob' => now()->subYears(8 + ($this->icSeq % 6))->subMonths($this->icSeq % 12)->toDateString(),
            'gender' => $this->icSeq % 2 === 0 ? 'male' : 'female',
            'is_active' => true,
        ]);
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function weekdayDates(Carbon $monthStart, int $isoWeekday, int $limit = 4): Collection
    {
        $dates = collect();
        $cursor = $monthStart->copy()->startOfMonth();
        $end = $monthStart->copy()->endOfMonth();

        while ($cursor->lte($end)) {
            if ($cursor->dayOfWeekIso === $isoWeekday) {
                $dates->push($cursor->copy());
            }
            $cursor->addDay();
        }

        return $dates->take($limit)->values();
    }

    private function coachForIndex(int $index): int
    {
        $names = ['Farid', 'Amir', 'Lena'];

        return $this->coaches[$names[$index % 3]]->id;
    }
}
