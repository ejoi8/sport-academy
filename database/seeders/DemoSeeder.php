<?php

namespace Database\Seeders;

use App\Models\AssessmentScore;
use App\Models\Offering;
use App\Models\Program;
use App\Models\Skill;
use App\Models\Sport;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * A living, realistic demo dataset anchored to today's date — the academy's real three programs
 * (Group, 1-on-1, Goalkeeper) played out over ~5 years:
 *
 *  - PAST (from each programme's launch up to last month): near-capacity rosters with churn
 *    (students join and leave over time), four recorded weekly sessions a month with a realistic
 *    attendance mix, and rubric scores that climb with each student's tenure. Fees step up over
 *    the years to today's RM160 / RM240 / RM120; each month's enrolment is priced at the fee then.
 *  - CURRENT month: enrolments only — no sessions recorded yet (even past weekends this month stay
 *    empty), so a coach can walk through recording from scratch.
 *  - NEXT month: near-capacity renewals (Active), no sessions.
 *
 * Every student is linked to a parent account (siblings cluster into families); guardian name/phone
 * mirror the parent. Logins: admin@admin.com (super-admin) + coach@coach.com / amir@ / lena@ /
 * hafiz@academy.test (coach), all "password".
 *
 * Volume at the default 60-month history is large (~90k scores) but seeds via batched inserts; the
 * seeder tests lower {@see static::$historyMonths} to stay fast.
 */
class DemoSeeder extends Seeder
{
    /** Months of recorded history to generate (5 years). Tests lower this for speed. */
    public static int $historyMonths = 60;

    /** @var Collection<int, Skill> */
    private Collection $skills;

    /** @var array<string, User> name => coach */
    private array $coaches = [];

    private string $password = '';

    /** @var array<int, int> parent user ids, for the bulk role assignment */
    private array $parentIds = [];

    /** @var array<int, array{parent:User, name:string, kids:int}> families with room for siblings */
    private array $families = [];

    private int $parentSeq = 0;

    private int $studentSeq = 0;

    private array $studentFirst = ['Adam', 'Haziq', 'Aisyah', 'Danish', 'Iman', 'Aryan', 'Sofia', 'Hana', 'Ammar', 'Zara', 'Irfan', 'Aleeya', 'Rayyan', 'Harith', 'Nadia', 'Zayd', 'Alya', 'Ariff', 'Balqis', 'Luqman'];

    private array $parentFirst = ['Rahman', 'Faizal', 'Suhaila', 'Azman', 'Roslina', 'Kamal', 'Halim', 'Zaidi', 'Noraini', 'Farah', 'Salleh', 'Hafizah'];

    private array $familyName = ['Ismail', 'Yusof', 'Tan', 'Lim', 'Kumar', 'Hassan', 'Abdullah', 'Zainal', 'Wong', 'Musa', 'Karim', 'Othman', 'Rashid', 'Chandran', 'Lee'];

    public function run(): void
    {
        $this->call([RoleSeeder::class, RubricSeeder::class]);

        // Deterministic so re-seeds and test runs are reproducible.
        mt_srand(20260101);

        $sport = Sport::where('name', 'Football')->firstOrFail();
        $this->skills = Skill::query()->orderBy('sort_order')->get();
        $this->password = Hash::make('password');

        $this->createAdmin();
        $this->createCoaches();
        $programs = $this->createPrograms($sport);

        foreach ($this->plan($programs) as $slot) {
            $this->seedSlot($slot);
        }

        $this->assignParentRole();
    }

    private function createAdmin(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            ['name' => 'Admin', 'phone' => '012-000 0001', 'password' => $this->password, 'email_verified_at' => now()],
        )->assignRole('super_admin');
    }

    private function createCoaches(): void
    {
        $sequence = 1;

        foreach (['Farid' => 'coach@coach.com', 'Amir' => 'amir@academy.test', 'Lena' => 'lena@academy.test', 'Hafiz' => 'hafiz@academy.test'] as $name => $email) {
            $coach = User::firstOrCreate(
                ['email' => $email],
                ['name' => 'Coach '.$name, 'phone' => '012-000 100'.$sequence++, 'password' => $this->password, 'email_verified_at' => now()],
            );
            $coach->assignRole('coach');
            $this->coaches[$name] = $coach;
        }
    }

    /**
     * The three real programmes at today's fees (the Program row holds the current price; each
     * month's offering carries the historical fee). Walk-in = 1.25x the per-session rate.
     *
     * @return array<string, Program>
     */
    private function createPrograms(Sport $sport): array
    {
        return [
            'group' => Program::create(['sport_id' => $sport->id, 'name' => 'Group', 'base_price_sen' => 16000, 'walk_in_fee_sen' => 5000, 'default_sessions' => 4]),
            'one2one' => Program::create(['sport_id' => $sport->id, 'name' => '1-on-1', 'base_price_sen' => 24000, 'walk_in_fee_sen' => 7500, 'default_sessions' => 4]),
            'goalkeeper' => Program::create(['sport_id' => $sport->id, 'name' => 'Goalkeeper', 'base_price_sen' => 12000, 'walk_in_fee_sen' => 3750, 'default_sessions' => 4]),
        ];
    }

    /**
     * One entry per weekly slot. `launch` is capped at the configured history so a short test seed
     * still starts every slot inside its window. `fees` maps "months-ago threshold => price"; the
     * newest matching threshold wins (see {@see feeFor}).
     *
     * @param  array<string, Program>  $programs
     * @return array<int, array<string, mixed>>
     */
    private function plan(array $programs): array
    {
        $h = static::$historyMonths;

        return [
            ['program' => $programs['group'], 'weekday' => 6, 'start' => '16:00', 'end' => '18:00', 'cap' => 40, 'fill' => 0.9, 'coach' => 'Amir', 'launch' => min(60, $h), 'fees' => [36 => 12000, 18 => 14000, 0 => 16000]],
            ['program' => $programs['one2one'], 'weekday' => 6, 'start' => '16:00', 'end' => '18:00', 'cap' => 12, 'fill' => 0.9, 'coach' => 'Farid', 'launch' => min(42, $h), 'fees' => [18 => 20000, 0 => 24000]],
            ['program' => $programs['one2one'], 'weekday' => 7, 'start' => '09:00', 'end' => '11:00', 'cap' => 12, 'fill' => 0.9, 'coach' => 'Lena', 'launch' => min(42, $h), 'fees' => [18 => 20000, 0 => 24000]],
            ['program' => $programs['goalkeeper'], 'weekday' => 6, 'start' => '16:00', 'end' => '18:00', 'cap' => 12, 'fill' => 0.9, 'coach' => 'Hafiz', 'launch' => min(18, $h), 'fees' => [6 => 10000, 0 => 12000]],
            ['program' => $programs['goalkeeper'], 'weekday' => 7, 'start' => '09:00', 'end' => '11:00', 'cap' => 12, 'fill' => 0.9, 'coach' => 'Hafiz', 'launch' => min(18, $h), 'fees' => [6 => 10000, 0 => 12000]],
        ];
    }

    /**
     * Walk one slot from launch to next month: evolve a near-capacity roster with churn, write an
     * offering + enrolments each month, and record four sessions a month for past months only.
     *
     * @param  array<string, mixed>  $slot
     */
    private function seedSlot(array $slot): void
    {
        $target = max(1, (int) round($slot['cap'] * $slot['fill']));
        $coachId = $this->coaches[$slot['coach']]->id;
        $roster = []; // student_id => ['student' => Student, 'baseline' => float, 'start' => int months-ago]

        for ($m = $slot['launch']; $m >= -1; $m--) {
            $monthStart = now()->startOfMonth()->addMonthsNoOverflow(-$m); // m=-1 => next month
            $feeSen = $this->feeFor($slot['fees'], $m);

            // ~8% of the roster leaves each month (never on the launch month); the student goes
            // inactive, their history stays. Then refill to the target with fresh joiners.
            if ($m < $slot['launch']) {
                foreach ($roster as $sid => $entry) {
                    if (mt_rand(1, 100) <= 8) {
                        $entry['student']->update(['is_active' => false]);
                        unset($roster[$sid]);
                    }
                }
            }

            while (count($roster) < $target) {
                $entry = $this->makeStudent($m);
                $roster[$entry['student']->id] = $entry;
            }

            $offering = Offering::create([
                'program_id' => $slot['program']->id,
                'period' => $monthStart->format('Y-m'),
                'schedule_type' => 'recurring',
                'weekday' => $slot['weekday'],
                'start_time' => $slot['start'],
                'end_time' => $slot['end'],
                'capacity' => $slot['cap'],
                'session_count' => 4,
                'price_sen' => $feeSen,
                'default_coach_id' => $coachId,
                'is_open' => true,
            ]);

            // Registration is dated to the month it's for (next month = registered now), so any
            // created_at-based reporting lines up with the real timeline.
            $enrolledAt = ($m >= 0 ? $monthStart : now())->toDateTimeString();
            DB::table('enrollments')->insert(array_map(fn (int $sid): array => [
                'student_id' => $sid,
                'offering_id' => $offering->id,
                'status' => 'active',
                'price_sen' => $feeSen,
                'sessions_included' => 4,
                'created_at' => $enrolledAt,
                'updated_at' => $enrolledAt,
            ], array_keys($roster)));

            /** @var array<int, int> student_id => enrollment_id */
            $enrIds = DB::table('enrollments')->where('offering_id', $offering->id)->pluck('id', 'student_id')->all();

            // Past months are recorded; the current and next month keep enrolments only.
            if ($m >= 1) {
                foreach ($this->weekdayDates($monthStart, $slot['weekday']) as $sessionIndex => $date) {
                    $this->recordSession($offering->id, $date, $sessionIndex, $roster, $enrIds, $coachId, $m);
                }
            }
        }
    }

    /**
     * Insert one session's attendance (realistic status mix) and, for present/late only, the rubric
     * scores — which climb with the student's tenure. All batched to keep the big seed fast.
     *
     * @param  array<int, array{student:Student, baseline:float, start:int}>  $roster
     * @param  array<int, int>  $enrIds  student_id => enrollment_id
     */
    private function recordSession(int $offeringId, Carbon $date, int $sessionIndex, array $roster, array $enrIds, int $coachId, int $monthsAgo): void
    {
        // Stamp the session/attendance/score rows with the session's own date so created_at-based
        // reports (score trend, monthly stats) reflect the real 5-year timeline, not the seed run.
        $now = $date->toDateTimeString();

        $sessionId = DB::table('training_sessions')->insertGetId([
            'offering_id' => $offeringId,
            'session_date' => $date->toDateString(),
            'coach_id' => $coachId,
            'created_by' => $coachId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('attendances')->insert(array_map(fn (int $sid): array => [
            'training_session_id' => $sessionId,
            'student_id' => $sid,
            'enrollment_id' => $enrIds[$sid] ?? null,
            'participant_type' => 'enrolled',
            'status' => $this->rollAttendance(),
            'coach_id' => $coachId,
            'walk_in_fee_sen' => null,
            'marked_by' => $coachId,
            'created_at' => $now,
            'updated_at' => $now,
        ], array_keys($roster)));

        $scoreRows = [];

        foreach (DB::table('attendances')->where('training_session_id', $sessionId)->get(['id', 'student_id', 'status']) as $attendance) {
            if (! in_array($attendance->status, ['present', 'late'], true)) {
                continue; // scores aren't recorded for absent/excused
            }

            $entry = $roster[$attendance->student_id];
            $tenure = max(0, $entry['start'] - $monthsAgo); // months trained by this session

            foreach ($this->skills as $skill) {
                $scoreRows[] = [
                    'attendance_id' => $attendance->id,
                    'skill_id' => $skill->id,
                    'score' => $this->scoreFor($entry['baseline'], $tenure, $skill->id, $sessionIndex),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($scoreRows, 2000) as $chunk) {
            DB::table('assessment_scores')->insert($chunk);
        }
    }

    /** Present-heavy mix: ~85% present, 7% late, 5% absent, 3% excused. */
    private function rollAttendance(): string
    {
        $r = mt_rand(1, 100);

        return $r <= 85 ? 'present' : ($r <= 92 ? 'late' : ($r <= 97 ? 'absent' : 'excused'));
    }

    /** A 1–5 rubric score that starts ~2 and improves with tenure, with a little per-skill spread. */
    private function scoreFor(float $baseline, int $tenureMonths, int $skillId, int $sessionIndex): int
    {
        $value = $baseline
            + min(2.5, $tenureMonths / 12)          // ~+1 per year, capped
            + (($skillId % 3) - 1) * 0.3            // some skills stronger than others
            + $sessionIndex * 0.05                  // slight climb within the month
            + mt_rand(-3, 3) / 10;                  // noise

        return max(1, min(5, (int) round($value)));
    }

    /** The price that applies `$monthsAgo` months back (current/next month use the newest fee). */
    private function feeFor(array $fees, int $monthsAgo): int
    {
        $monthsAgo = max(0, $monthsAgo);
        krsort($fees);

        foreach ($fees as $threshold => $price) {
            if ($monthsAgo >= $threshold) {
                return $price;
            }
        }

        return (int) reset($fees);
    }

    /**
     * Create a student inside a family — 25% of the time a sibling of an existing family (shared
     * parent + surname), otherwise a brand-new family. Guardian fields mirror the parent account.
     *
     * @return array{student:Student, baseline:float, start:int}
     */
    private function makeStudent(int $startMonthsAgo): array
    {
        $family = $this->resolveFamily();
        $parent = $family['parent'];
        $seq = ++$this->studentSeq;

        $student = Student::create([
            'parent_id' => $parent->id,
            'name' => $this->studentFirst[array_rand($this->studentFirst)].' '.$family['name'],
            'ic_number' => '07'.str_pad((string) $seq, 10, '0', STR_PAD_LEFT),
            'dob' => now()->subYears(mt_rand(6, 15))->subMonths(mt_rand(0, 11))->toDateString(),
            'gender' => mt_rand(0, 1) === 0 ? 'male' : 'female',
            'guardian_name' => $parent->name,
            'guardian_phone' => $parent->phone,
            'is_active' => true,
        ]);

        return ['student' => $student, 'baseline' => mt_rand(15, 25) / 10, 'start' => $startMonthsAgo];
    }

    /**
     * @return array{parent:User, name:string, kids:int}
     */
    private function resolveFamily(): array
    {
        $withRoom = array_filter($this->families, fn (array $family): bool => $family['kids'] < 3);

        if ($withRoom !== [] && mt_rand(1, 100) <= 25) {
            $key = array_rand($withRoom);
            $this->families[$key]['kids']++;

            return $this->families[$key];
        }

        $surname = $this->familyName[array_rand($this->familyName)];
        $family = ['parent' => $this->makeParent($surname), 'name' => $surname, 'kids' => 1];
        $this->families[] = $family;

        return $family;
    }

    private function makeParent(string $surname): User
    {
        $n = ++$this->parentSeq;

        $parent = User::create([
            'name' => $this->parentFirst[array_rand($this->parentFirst)].' '.$surname,
            'email' => 'parent'.$n.'@demo.test',
            'phone' => sprintf('013-%03d %04d', intdiv($n, 10000), $n % 10000),
            'password' => $this->password,
            'email_verified_at' => now(),
        ]);

        $this->parentIds[] = $parent->id;

        return $parent;
    }

    /** Assign the 'parent' role to every seeded parent in one insert (avoids per-call cache churn). */
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

    /**
     * The (up to four) dates in a month that fall on the given ISO weekday.
     *
     * @return Collection<int, Carbon>
     */
    private function weekdayDates(Carbon $monthStart, int $isoWeekday): Collection
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

        return $dates->take(4)->values();
    }
}
