<?php

namespace Database\Seeders;

use App\Models\AssessmentScore;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\Program;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\Sport;
use App\Models\Student;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Curated, scenario-tagged demo data.
 *
 * June (last month) is a completed month with attendance + rubric scores;
 * July (this month) holds fresh registrations whose sessions are still upcoming.
 * Parents/children are named "S# …" so each scenario is recognisable on the roster.
 */
class DemoSeeder extends Seeder
{
    /** @var Collection<int, Skill> */
    private Collection $skills;

    /** @var array<string, User> */
    private array $coaches = [];

    /** @var array<string, Offering> */
    private array $offerings = [];

    /** @var array<string, array<string, mixed>> */
    private array $slots = [];

    private ?Student $walkIn = null;

    private ?Student $makeUp = null;

    /** @var array<int, int> */
    private array $allAbsentStudentIds = [];

    private int $icSeq = 0;

    public function run(): void
    {
        $this->createRolesAndCoaches();

        $sport = Sport::create(['name' => 'Football']);
        $this->createRubric($sport);
        $programs = $this->createPrograms($sport);

        $currentMonth = now()->startOfMonth();
        $historyMonth = now()->startOfMonth()->subMonthNoOverflow();
        $currentPeriod = $currentMonth->format('Y-m');
        $historyPeriod = $historyMonth->format('Y-m');

        $this->slots = [
            '1on1-sat' => ['program' => '1-on-1', 'weekday' => 6, 'start' => '09:00', 'end' => '10:00', 'cap' => 3, 'price' => 28000, 'coach' => 'Farid', 'label' => '1on1·Sat'],
            '1on1-sun' => ['program' => '1-on-1', 'weekday' => 7, 'start' => '09:00', 'end' => '10:00', 'cap' => 3, 'price' => 28000, 'coach' => 'Amir', 'label' => '1on1·Sun'],
            'group-wed' => ['program' => 'Group', 'weekday' => 3, 'start' => '18:00', 'end' => '19:30', 'cap' => 14, 'price' => 12000, 'coach' => 'Farid', 'label' => 'Group·Wed'],
            'group-sat' => ['program' => 'Group', 'weekday' => 6, 'start' => '10:00', 'end' => '11:30', 'cap' => 8, 'price' => 12000, 'coach' => 'Amir', 'label' => 'Group·Sat'],
            'group-sun' => ['program' => 'Group', 'weekday' => 7, 'start' => '17:00', 'end' => '18:30', 'cap' => 14, 'price' => 12000, 'coach' => 'Lena', 'label' => 'Group·Sun'],
            'group-mon-trial' => ['program' => 'Group', 'weekday' => 1, 'start' => '18:00', 'end' => '19:30', 'cap' => 10, 'price' => 12000, 'coach' => 'Farid', 'label' => 'TRIAL·Mon'],
            'gk-thu' => ['program' => 'Goalkeeper', 'weekday' => 4, 'start' => '18:00', 'end' => '19:00', 'cap' => 8, 'price' => 15000, 'coach' => 'Lena', 'label' => 'GK·Thu'],
        ];

        // Recurring offerings: June (history) excludes the July-only trial slot.
        foreach ($this->slots as $code => $cfg) {
            if ($code !== 'group-mon-trial') {
                $this->offerings["$historyPeriod|$code"] = $this->makeOffering($programs, $cfg, $historyPeriod);
            }
            $this->offerings["$currentPeriod|$code"] = $this->makeOffering($programs, $cfg, $currentPeriod);
        }

        // One-off Striker Clinic (July, upcoming date).
        $this->offerings["$currentPeriod|striker"] = Offering::create([
            'program_id' => $programs['Striker Clinic']->id,
            'period' => $currentPeriod,
            'schedule_type' => 'one_off',
            'specific_date' => $currentMonth->copy()->addDays(16)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'capacity' => 20,
            'price_sen' => 9000,
            'default_coach_id' => $this->coaches['Lena']->id,
            'is_open' => true,
        ]);

        $this->createFamilies($currentPeriod, $historyPeriod);

        // A walk-in (no parent) used in one June session.
        $this->walkIn = $this->makeStudent(null, 'WK Zara (walk-in)');

        $this->generateHistory($historyMonth, $historyPeriod);
    }

    private function createRolesAndCoaches(): void
    {
        foreach (['admin', 'coach', 'parent', 'super_admin'] as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (['Farid' => 'coach@academy.test', 'Amir' => 'amir@academy.test', 'Lena' => 'lena@academy.test'] as $name => $email) {
            $coach = User::firstOrCreate(
                ['email' => $email],
                ['name' => 'Coach '.$name, 'password' => Hash::make('password'), 'email_verified_at' => now()],
            );
            $coach->assignRole('super_admin');
            $coach->assignRole('coach');
            $this->coaches[$name] = $coach;
        }
    }

    private function createRubric(Sport $sport): void
    {
        $rubric = [
            'Technical' => ['Passing', 'Shooting', 'Dribbling', 'First touch'],
            'Physical' => ['Speed', 'Stamina'],
            'Mental' => ['Teamwork'],
        ];

        $sort = 0;
        foreach ($rubric as $categoryName => $skillNames) {
            $category = SkillCategory::create(['sport_id' => $sport->id, 'name' => $categoryName, 'sort_order' => $sort++]);
            foreach ($skillNames as $skillName) {
                Skill::create(['sport_id' => $sport->id, 'skill_category_id' => $category->id, 'name' => $skillName, 'sort_order' => $sort++]);
            }
        }

        $this->skills = Skill::query()->orderBy('sort_order')->get();
    }

    /**
     * @return array<string, Program>
     */
    private function createPrograms(Sport $sport): array
    {
        return [
            '1-on-1' => Program::create(['sport_id' => $sport->id, 'name' => '1-on-1', 'base_price_sen' => 28000, 'walk_in_fee_sen' => 8000]),
            'Group' => Program::create(['sport_id' => $sport->id, 'name' => 'Group Training', 'base_price_sen' => 12000, 'walk_in_fee_sen' => 4000]),
            'Goalkeeper' => Program::create(['sport_id' => $sport->id, 'name' => 'Goalkeeper', 'base_price_sen' => 15000, 'walk_in_fee_sen' => 5000]),
            'Striker Clinic' => Program::create(['sport_id' => $sport->id, 'name' => 'Striker Clinic', 'base_price_sen' => 9000, 'walk_in_fee_sen' => 3000]),
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
            'price_sen' => $cfg['price'],
            'default_coach_id' => $this->coaches[$cfg['coach']]->id,
            'is_open' => true,
        ]);
    }

    private function createFamilies(string $currentPeriod, string $historyPeriod): void
    {
        $families = [
            ['code' => 'S1', 'surname' => 'Rahman', 'children' => [
                ['name' => 'Adam', 'slots' => ['1on1-sat'], 'history' => true],
            ]],
            ['code' => 'S2', 'surname' => 'Tan', 'children' => [
                ['name' => 'Bella', 'slots' => ['1on1-sun'], 'history' => true, 'makeup' => true],
                ['name' => 'Cara', 'slots' => ['group-wed'], 'history' => true],
            ]],
            ['code' => 'S3', 'surname' => 'Kumar', 'children' => [
                ['name' => 'Danish', 'slots' => ['group-wed', 'gk-thu'], 'history' => true],
            ]],
            ['code' => 'S4', 'surname' => 'Lim', 'children' => [
                ['name' => 'Evan', 'slots' => ['group-sat'], 'history' => true],
                ['name' => 'Faiz', 'slots' => ['group-sat'], 'history' => true],
            ]],
            ['code' => 'S5', 'surname' => 'Wong', 'children' => [
                ['name' => 'Gina', 'slots' => ['1on1-sat'], 'history' => true],
                ['name' => 'Hana', 'slots' => ['group-sun'], 'history' => true],
                ['name' => 'Ivan', 'slots' => ['gk-thu'], 'history' => true],
            ]],
            ['code' => 'S6', 'surname' => 'Ismail', 'children' => [
                ['name' => 'Jay', 'slots' => ['group-wed'], 'history' => true, 'julyStatus' => 'pending'],
            ]],
            ['code' => 'S7', 'surname' => 'Abdullah', 'children' => [
                ['name' => 'Kira', 'slots' => ['1on1-sun'], 'history' => true, 'julyStatus' => 'overdue', 'allAbsent' => true],
            ]],
            ['code' => 'S8', 'surname' => 'Chan', 'children' => [
                ['name' => 'Leo', 'slots' => ['striker'], 'history' => false],
            ]],
            ['code' => 'S9', 'surname' => 'Ng', 'children' => [
                ['name' => 'Omar', 'slots' => ['group-sat'], 'history' => true],
                ['name' => 'Putra', 'slots' => ['group-sat'], 'history' => true],
                ['name' => 'Qasim', 'slots' => ['group-sat'], 'history' => true],
                ['name' => 'Rania', 'slots' => ['group-sat'], 'history' => true],
                ['name' => 'Sam', 'slots' => ['group-sat'], 'history' => true],
            ]],
            ['code' => 'S10', 'surname' => 'FreshStart', 'children' => [
                ['name' => 'Mia', 'slots' => ['group-mon-trial'], 'history' => false],
                ['name' => 'Nael', 'slots' => ['group-mon-trial'], 'history' => false],
            ]],
        ];

        foreach ($families as $family) {
            $parent = User::create([
                'name' => $family['code'].' '.$family['surname'],
                'email' => strtolower($family['code']).'.'.Str::slug($family['surname']).'@demo.test',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);
            $parent->assignRole('parent');

            foreach ($family['children'] as $child) {
                $labels = array_map(fn (string $code): string => $this->slotLabel($code), $child['slots']);
                $student = $this->makeStudent($parent->id, $family['code'].' '.$child['name'].' ('.implode('+', $labels).')');

                if (! empty($child['makeup'])) {
                    $this->makeUp = $student;
                }
                if (! empty($child['allAbsent'])) {
                    $this->allAbsentStudentIds[] = $student->id;
                }

                foreach ($child['slots'] as $code) {
                    $oneOff = $code === 'striker';
                    $julyKey = $oneOff ? "$currentPeriod|striker" : "$currentPeriod|$code";
                    $julyOffering = $this->offerings[$julyKey];

                    Enrollment::firstOrCreate(
                        ['student_id' => $student->id, 'offering_id' => $julyOffering->id],
                        [
                            'status' => $child['julyStatus'] ?? 'active',
                            'price_sen' => $julyOffering->price_sen,
                            'sessions_included' => $oneOff ? 1 : 4,
                        ],
                    );

                    if (! empty($child['history']) && ! $oneOff && isset($this->offerings["$historyPeriod|$code"])) {
                        $juneOffering = $this->offerings["$historyPeriod|$code"];
                        Enrollment::firstOrCreate(
                            ['student_id' => $student->id, 'offering_id' => $juneOffering->id],
                            ['status' => 'active', 'price_sen' => $juneOffering->price_sen, 'sessions_included' => 4],
                        );
                    }
                }
            }
        }
    }

    private function generateHistory(Carbon $historyMonth, string $historyPeriod): void
    {
        foreach ($this->slots as $code => $cfg) {
            if ($code === 'group-mon-trial') {
                continue; // July-only, no history
            }

            $offering = $this->offerings["$historyPeriod|$code"] ?? null;
            if (! $offering) {
                continue;
            }

            $enrolled = Enrollment::query()
                ->where('offering_id', $offering->id)
                ->whereIn('status', ['active', 'pending', 'overdue'])
                ->with('student')
                ->get();

            if ($enrolled->isEmpty()) {
                continue;
            }

            foreach ($this->weekdayDates($historyMonth, $cfg['weekday']) as $sessionIndex => $date) {
                $session = TrainingSession::create([
                    'offering_id' => $offering->id,
                    'session_date' => $date->toDateString(),
                    'coach_id' => $this->coaches[$cfg['coach']]->id,
                    'created_by' => $this->coaches['Farid']->id,
                ]);

                foreach ($enrolled->values() as $ei => $enrollment) {
                    $this->recordAttendance($session, $enrollment->student, $sessionIndex, $ei, 'enrolled');
                }

                // Walk-in in the 2nd Wednesday session; make-up in the 3rd Saturday session.
                if ($code === 'group-wed' && $sessionIndex === 1 && $this->walkIn) {
                    $this->recordAttendance($session, $this->walkIn, $sessionIndex, 90, 'walk_in', $offering->price_sen);
                }
                if ($code === 'group-sat' && $sessionIndex === 2 && $this->makeUp) {
                    $this->recordAttendance($session, $this->makeUp, $sessionIndex, 70, 'make_up');
                }
            }
        }
    }

    private function recordAttendance(TrainingSession $session, Student $student, int $sessionIndex, int $ei, string $type, ?int $walkInFeeSen = null): void
    {
        $absent = in_array($student->id, $this->allAbsentStudentIds, true)
            || ($ei % 8 === 0 && $sessionIndex === 3);
        $status = $absent ? 'absent' : (($ei % 6 === 0 && $sessionIndex === 1) ? 'late' : 'present');

        $attendance = Attendance::create([
            'training_session_id' => $session->id,
            'student_id' => $student->id,
            'participant_type' => $type,
            'status' => $status,
            'coach_id' => $this->coachForIndex($ei),
            'walk_in_fee_sen' => ($type === 'walk_in' && ! $absent) ? ($walkInFeeSen ?? 4000) : null,
            'marked_by' => $this->coaches['Farid']->id,
        ]);

        if ($absent) {
            return;
        }

        foreach ($this->skills as $skill) {
            // Scores climb across the month (session 1 ≈ 2, session 4 ≈ 5).
            $score = min(5, max(1, 1 + $sessionIndex + (($ei + $skill->id) % 2)));
            AssessmentScore::create(['attendance_id' => $attendance->id, 'skill_id' => $skill->id, 'score' => $score]);
        }
    }

    private function makeStudent(?int $parentId, string $name): Student
    {
        return Student::create([
            'parent_id' => $parentId,
            'name' => $name,
            'ic_number' => '07'.str_pad((string) (++$this->icSeq), 10, '0', STR_PAD_LEFT),
            'dob' => now()->subYears(8 + ($this->icSeq % 6))->subMonths($this->icSeq)->toDateString(),
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

        return $dates->take($limit);
    }

    private function coachForIndex(int $index): int
    {
        $names = ['Farid', 'Amir', 'Lena'];

        return $this->coaches[$names[$index % 3]]->id;
    }

    private function slotLabel(string $code): string
    {
        return $code === 'striker' ? 'Striker' : ($this->slots[$code]['label'] ?? $code);
    }
}
