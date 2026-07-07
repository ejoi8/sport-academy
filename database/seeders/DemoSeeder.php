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
 *  - Two recurring weekly programs on the academy's real weekend timetable — Group (Sabtu
 *    petang, Sat 16:00–18:00, cap 40) and 1-on-1 (Sabtu petang Sat 16:00–18:00 and Ahad pagi
 *    Sun 09:00–11:00, cap 12 each) — 4 sessions a month each.
 *  - One Football Clinic one-off (the month's 2nd Saturday afternoon).
 *  - A cohort of students per slot sized under that slot's capacity (not a flat 100), each
 *    attached to a parent account.
 *  - An admin login: admin@admin.com / password.
 *
 * Last month's occurrences are recorded (attendance + rubric scores); this month's are recorded
 * up to today, leaving upcoming dates empty so the "not recorded yet" state shows.
 */
class DemoSeeder extends Seeder
{
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

    // --- Scenario layer (kept out of $slots/$offerings so the generic history/current-to-date
    // loops never touch it — every scenario session is scripted explicitly for determinism). ---
    private ?Offering $scenarioSlotHistory = null;

    private ?Offering $scenarioSlotCurrent = null;

    /** @var array<int, TrainingSession> S1..S4, in date order. */
    private array $scenarioSessions = [];

    private ?Enrollment $gopalEnrollment = null;

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

        // The academy's real weekend timetable: a Group class on Sabtu petang (Sat 16:00–18:00)
        // and 1-on-1 coaching on both Sabtu petang and Ahad pagi (Sun 09:00–11:00). 'cohort' is
        // sized under 'cap' (not a flat number) so the demo shows realistic headroom per slot.
        $this->slots = [
            'group-sat' => ['program' => 'group', 'weekday' => 6, 'start' => '16:00', 'end' => '18:00', 'cap' => 40, 'price' => 12000, 'coach' => 'Amir', 'label' => 'Group Sat', 'cohort' => 32],
            'one2one-sat' => ['program' => 'one2one', 'weekday' => 6, 'start' => '16:00', 'end' => '18:00', 'cap' => 12, 'price' => 30000, 'coach' => 'Farid', 'label' => '1-on-1 Sat', 'cohort' => 8],
            'one2one-sun' => ['program' => 'one2one', 'weekday' => 7, 'start' => '09:00', 'end' => '11:00', 'cap' => 12, 'price' => 30000, 'coach' => 'Lena', 'label' => '1-on-1 Sun', 'cohort' => 8],
        ];

        foreach ($this->slots as $code => $cfg) {
            $this->offerings["$historyPeriod|$code"] = $this->makeOffering($programs, $cfg, $historyPeriod);
            $this->offerings["$currentPeriod|$code"] = $this->makeOffering($programs, $cfg, $currentPeriod);
            $this->enrolFamilies(
                $this->offerings["$currentPeriod|$code"],
                $this->offerings["$historyPeriod|$code"],
                $cfg['label'],
                $cfg['cohort'],
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
        $this->enrolFamilies($clinic, null, 'Clinic', 100);

        // --- Scenario layer: every credit/attendance edge case, scripted deterministically. ---
        $this->createScenarioSlot($programs, $historyPeriod, $currentPeriod, $historyMonth);
        $this->createOffScheduleSession($historyMonth);
        $this->createRamadhanProgram($sport, $historyPeriod, $historyMonth);
        $this->createSecondTeamOverlap($programs, $currentMonth);

        $this->assignParentRole();

        $this->generateHistory($historyMonth, $historyPeriod);
        $this->generateCurrentToDate($currentMonth, $currentPeriod);

        // Gopal's cross-class make-up needs a MAIN offering's history session to already exist.
        $this->attachGopalMakeUp($historyPeriod);
    }

    private function createAdmin(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            ['name' => 'Admin', 'phone' => '012-000 0001', 'password' => $this->password, 'email_verified_at' => now()],
        );
        $admin->assignRole('super_admin');
    }

    private function createCoaches(): void
    {
        // Every login gets a phone — hosted gateways (e.g. toyyibPay) refuse a bill without one,
        // and staff accounts are regularly used to walk through the parent booking flow in demos.
        $sequence = 1;

        foreach (['Farid' => 'coach@academy.test', 'Amir' => 'amir@academy.test', 'Lena' => 'lena@academy.test', 'Hafiz' => 'hafiz@academy.test'] as $name => $email) {
            $coach = User::firstOrCreate(
                ['email' => $email],
                ['name' => 'Coach '.$name, 'phone' => '012-000 100'.$sequence++, 'password' => $this->password, 'email_verified_at' => now()],
            );
            $coach->assignRole('super_admin');
            $coach->assignRole('coach');
            $this->coaches[$name] = $coach;
        }
    }

    /**
     * The two real coaching programs — Group and 1-on-1 — plus the one-off Football Clinic.
     * Walk-in fees aren't part of the published plans; each is estimated as
     * (monthly price ÷ 4 sessions) × 1.25 — a single drop-in session costs a bit more than the
     * per-session subscription rate.
     *
     * @return array<string, Program>
     */
    private function createPrograms(Sport $sport): array
    {
        return [
            'group' => Program::create(['sport_id' => $sport->id, 'name' => 'Group', 'base_price_sen' => 12000, 'walk_in_fee_sen' => 3750, 'default_sessions' => 4]),
            'one2one' => Program::create(['sport_id' => $sport->id, 'name' => '1-on-1', 'base_price_sen' => 30000, 'walk_in_fee_sen' => 9375, 'default_sessions' => 4]),
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
     * Enrol $cohortSize students (in families of four, each under its own parent) into a session
     * — and into last month's offering too, when given, so history records. $cohortSize must be
     * a multiple of FAMILY_SIZE.
     */
    private function enrolFamilies(Offering $current, ?Offering $history, string $label, int $cohortSize): void
    {
        $child = 0;

        for ($f = 1; $f <= (int) ($cohortSize / self::FAMILY_SIZE); $f++) {
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

    /**
     * "Scenario slot" — a Group class closed for registration (is_open = false), created for both
     * months, proving closed classes still record and display. The history side gets its first 4
     * Saturdays recorded as sessions S1-S4; the current side gets enrolments but no recorded
     * sessions.
     *
     * @param  array<string, Program>  $programs
     */
    private function createScenarioSlot(array $programs, string $historyPeriod, string $currentPeriod, Carbon $historyMonth): void
    {
        $cfg = ['weekday' => 6, 'start' => '11:00', 'end' => '12:30', 'cap' => 10, 'price' => 12000, 'coach' => 'Lena'];

        $base = [
            'program_id' => $programs['group']->id,
            'schedule_type' => 'recurring',
            'weekday' => $cfg['weekday'],
            'start_time' => $cfg['start'],
            'end_time' => $cfg['end'],
            'capacity' => $cfg['cap'],
            'session_count' => 4,
            'price_sen' => $cfg['price'],
            'default_coach_id' => $this->coaches[$cfg['coach']]->id,
            'is_open' => false,
        ];

        $this->scenarioSlotHistory = Offering::create($base + ['period' => $historyPeriod]);
        $this->scenarioSlotCurrent = Offering::create($base + ['period' => $currentPeriod]);

        $this->scenarioSessions = $this->weekdayDates($historyMonth, $cfg['weekday'], 4)
            ->map(fn (Carbon $date): TrainingSession => TrainingSession::create([
                'offering_id' => $this->scenarioSlotHistory->id,
                'session_date' => $date->toDateString(),
                'coach_id' => $this->scenarioSlotHistory->default_coach_id,
                'created_by' => $this->coaches['Farid']->id,
            ]))
            ->all();

        $this->createScenarioStudents($historyMonth);
    }

    /**
     * The named scenario students — one credit/attendance edge case each. Every history-slot
     * enrolment shares sessions_included = 4 unless overridden.
     */
    private function createScenarioStudents(Carbon $historyMonth): void
    {
        $history = $this->scenarioSlotHistory;
        $current = $this->scenarioSlotCurrent;
        [$s1, $s2, $s3, $s4] = $this->scenarioSessions;

        // Askar — paid up: present S1-S4 -> 4/4.
        $askar = $this->makeStudent($this->makeParent('Scenario Askar')->id, 'SC Askar (paid-up)');
        $askarEnrol = $this->scenarioEnrol($askar, $history);
        foreach ($this->scenarioSessions as $session) {
            $this->scenarioAttendance($session, $askar, $askarEnrol, 'enrolled', 'present');
        }

        // Bilal — over +2: sessions_included=2, present S1-S4 -> 4/2 over.
        $bilal = $this->makeStudent($this->makeParent('Scenario Bilal')->id, 'SC Bilal (over +2)');
        $bilalEnrol = $this->scenarioEnrol($bilal, $history, sessionsIncluded: 2);
        foreach ($this->scenarioSessions as $session) {
            $this->scenarioAttendance($session, $bilal, $bilalEnrol, 'enrolled', 'present');
        }

        // Chan — carry +3: present S1 only -> 3 left. Also a fresh current-month enrolment, so
        // the July roster shows +3 carried.
        $chan = $this->makeStudent($this->makeParent('Scenario Chan')->id, 'SC Chan (carry +3)');
        $chanEnrol = $this->scenarioEnrol($chan, $history);
        $this->scenarioAttendance($s1, $chan, $chanEnrol, 'enrolled', 'present');
        $this->scenarioEnrol($chan, $current);

        // Dina — excused: present S1,S2; excused S3,S4 -> used 2/4 (excused never consumes).
        $dina = $this->makeStudent($this->makeParent('Scenario Dina')->id, 'SC Dina (excused)');
        $dinaEnrol = $this->scenarioEnrol($dina, $history);
        $this->scenarioAttendance($s1, $dina, $dinaEnrol, 'enrolled', 'present');
        $this->scenarioAttendance($s2, $dina, $dinaEnrol, 'enrolled', 'present');
        $this->scenarioAttendance($s3, $dina, $dinaEnrol, 'enrolled', 'excused');
        $this->scenarioAttendance($s4, $dina, $dinaEnrol, 'enrolled', 'excused');

        // Eddy — absent burns: present S1,S2; absent S3,S4 -> used 4/4 (a no-show still burns).
        $eddy = $this->makeStudent($this->makeParent('Scenario Eddy')->id, 'SC Eddy (absent burns)');
        $eddyEnrol = $this->scenarioEnrol($eddy, $history);
        $this->scenarioAttendance($s1, $eddy, $eddyEnrol, 'enrolled', 'present');
        $this->scenarioAttendance($s2, $eddy, $eddyEnrol, 'enrolled', 'present');
        $this->scenarioAttendance($s3, $eddy, $eddyEnrol, 'enrolled', 'absent');
        $this->scenarioAttendance($s4, $eddy, $eddyEnrol, 'enrolled', 'absent');

        // Fara — cancelled enrolment, but one recorded attendance: proves cancelled enrolments
        // keep history and the roster hydration guard.
        $fara = $this->makeStudent($this->makeParent('Scenario Fara')->id, 'SC Fara (cancelled)');
        $faraEnrol = $this->scenarioEnrol($fara, $history, status: 'cancelled');
        $this->scenarioAttendance($s1, $fara, $faraEnrol, 'enrolled', 'present');

        // Gopal — make-up: present S1,S2 on the scenario slot; a third credit is consumed via a
        // SAME-PROGRAM (Group) MAIN offering's session via attachGopalMakeUp(), once that
        // offering has recorded sessions. Make-up credits are same-program only (see credits-policy.md).
        $gopal = $this->makeStudent($this->makeParent('Scenario Gopal')->id, 'SC Gopal (make-up)');
        $this->gopalEnrollment = $this->scenarioEnrol($gopal, $history);
        $this->scenarioAttendance($s1, $gopal, $this->gopalEnrollment, 'enrolled', 'present');
        $this->scenarioAttendance($s2, $gopal, $this->gopalEnrollment, 'enrolled', 'present');

        // Hana — walk-in: no parent, no IC. Fee only when attended.
        $hana = $this->makeStudent(null, 'SC Hana (walk-in)', withIc: false);
        $this->scenarioAttendance($s2, $hana, null, 'walk_in', 'present', walkInFeeSen: 4000);
        $this->scenarioAttendance($s3, $hana, null, 'walk_in', 'absent', walkInFeeSen: null);

        // Iman — expired leftovers: present S1, credits expire at the history month's last day, so
        // the carry chip and make-up eligibility are both gone. Plus a fresh current enrolment.
        $iman = $this->makeStudent($this->makeParent('Scenario Iman')->id, 'SC Iman (expired)');
        $imanEnrol = $this->scenarioEnrol($iman, $history, creditsExpireAt: $historyMonth->copy()->endOfMonth());
        $this->scenarioAttendance($s1, $iman, $imanEnrol, 'enrolled', 'present');
        $this->scenarioEnrol($iman, $current);

        // Jaya — pending, current-month only, no attendance.
        $jaya = $this->makeStudent($this->makeParent('Scenario Jaya')->id, 'SC Jaya (pending)');
        $this->scenarioEnrol($jaya, $current, status: 'pending');

        // Kila — overdue, current-month only, no attendance.
        $kila = $this->makeStudent($this->makeParent('Scenario Kila')->id, 'SC Kila (overdue)');
        $this->scenarioEnrol($kila, $current, status: 'overdue');
    }

    /** Off-schedule recording — the scenario slot (a Saturday class) run on a Tuesday. */
    private function createOffScheduleSession(Carbon $historyMonth): void
    {
        $secondTuesday = $this->weekdayDates($historyMonth, 2, 2)->last();
        if (! $secondTuesday || ! $this->scenarioSlotHistory) {
            return;
        }

        $session = TrainingSession::create([
            'offering_id' => $this->scenarioSlotHistory->id,
            'session_date' => $secondTuesday->toDateString(),
            'coach_id' => $this->scenarioSlotHistory->default_coach_id,
            'created_by' => $this->coaches['Farid']->id,
        ]);

        foreach (['SC Tue WalkIn 1', 'SC Tue WalkIn 2'] as $name) {
            $walkIn = $this->makeStudent(null, $name);
            $this->scenarioAttendance($session, $walkIn, null, 'walk_in', 'present', walkInFeeSen: 4000);
        }
    }

    /**
     * A one-month, inactive program: proves it keeps history but is hidden from Run Training's
     * ad-hoc program picker. One history-only offering, 3 new students under a shared parent, and
     * the first 2 Tuesdays recorded (all present).
     */
    private function createRamadhanProgram(Sport $sport, string $historyPeriod, Carbon $historyMonth): void
    {
        $program = Program::create([
            'sport_id' => $sport->id,
            'name' => 'Ramadhan Special',
            'base_price_sen' => 8000,
            'walk_in_fee_sen' => 3000,
            'default_sessions' => 4,
            'is_active' => false,
        ]);

        $offering = Offering::create([
            'program_id' => $program->id,
            'period' => $historyPeriod,
            'schedule_type' => 'recurring',
            'weekday' => 2,
            'start_time' => '21:30',
            'end_time' => '22:30',
            'capacity' => 15,
            'session_count' => 4,
            'price_sen' => 8000,
            'default_coach_id' => $this->coaches['Farid']->id,
            'is_open' => true,
        ]);

        $parent = $this->makeParent('Ramadhan family');
        $enrolments = collect(range(1, 3))->map(function (int $i) use ($parent, $offering): Enrollment {
            $student = $this->makeStudent($parent->id, 'Ramadhan Student '.$i);

            return $this->scenarioEnrol($student, $offering);
        })->values();

        foreach ($this->weekdayDates($historyMonth, 2, 2) as $sessionIndex => $date) {
            $session = TrainingSession::create([
                'offering_id' => $offering->id,
                'session_date' => $date->toDateString(),
                'coach_id' => $offering->default_coach_id,
                'created_by' => $this->coaches['Farid']->id,
            ]);

            foreach ($enrolments as $enrolment) {
                $this->scenarioAttendance($session, $enrolment->student, $enrolment, 'enrolled', 'present');
            }
        }
    }

    /**
     * Second-team overlap: a one-off Group offering on the same date+time as the main recurring
     * Group Sat 16:00-18:00 class, proving two same-time cards render for one date. Only recorded
     * (with walk-ins) if that Saturday has already happened.
     *
     * @param  array<string, Program>  $programs
     */
    private function createSecondTeamOverlap(array $programs, Carbon $currentMonth): void
    {
        $firstSaturday = $currentMonth->copy()->startOfMonth();
        while ($firstSaturday->dayOfWeekIso !== 6) {
            $firstSaturday->addDay();
        }

        $offering = Offering::create([
            'program_id' => $programs['group']->id,
            'period' => $currentMonth->format('Y-m'),
            'schedule_type' => 'one_off',
            'specific_date' => $firstSaturday->toDateString(),
            'start_time' => '16:00',
            'end_time' => '18:00',
            'capacity' => 0,
            'session_count' => 4,
            'price_sen' => 12000,
            'default_coach_id' => $this->coaches['Amir']->id,
            'is_open' => true,
        ]);

        if ($firstSaturday->gt(today())) {
            return;
        }

        $session = TrainingSession::create([
            'offering_id' => $offering->id,
            'session_date' => $firstSaturday->toDateString(),
            'coach_id' => $offering->default_coach_id,
            'created_by' => $this->coaches['Farid']->id,
        ]);

        foreach (['SC Team B WalkIn 1', 'SC Team B WalkIn 2', 'SC Team B WalkIn 3'] as $name) {
            $walkIn = $this->makeStudent(null, $name);
            $this->scenarioAttendance($session, $walkIn, null, 'walk_in', 'present', walkInFeeSen: 4000);
        }
    }

    /**
     * Gopal's third credit, consumed via a same-program make-up: an attendance recorded against
     * his scenario enrolment (Group, via the scenario slot) but attached to the MAIN **Group**
     * offering's already-recorded history session. Must run after generateHistory() so that
     * session exists. He is never enrolled in that offering. Make-up credits are same-program
     * only (see credits-policy.md) — his pool is a Group credit, so it can only pay for a Group
     * session.
     */
    private function attachGopalMakeUp(string $historyPeriod): void
    {
        if (! $this->gopalEnrollment) {
            return;
        }

        $mainOffering = $this->offerings["$historyPeriod|group-sat"] ?? null;
        $session = $mainOffering?->trainingSessions()->orderBy('session_date')->first();

        if (! $session) {
            return;
        }

        $this->scenarioAttendance($session, $this->gopalEnrollment->student, $this->gopalEnrollment, 'make_up', 'present');
    }

    private function scenarioEnrol(Student $student, Offering $offering, string $status = 'active', ?int $sessionsIncluded = null, ?Carbon $creditsExpireAt = null): Enrollment
    {
        return Enrollment::create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => $status,
            'price_sen' => $offering->price_sen,
            'sessions_included' => $sessionsIncluded ?? $offering->session_count,
            'credits_expire_at' => $creditsExpireAt,
        ]);
    }

    /** Records one attendance and, for present/late only, bulk-inserts rubric scores. */
    private function scenarioAttendance(TrainingSession $session, Student $student, ?Enrollment $enrollment, string $participantType, string $status, ?int $walkInFeeSen = null): Attendance
    {
        $attendance = Attendance::create([
            'training_session_id' => $session->id,
            'student_id' => $student->id,
            'enrollment_id' => $enrollment?->id,
            'participant_type' => $participantType,
            'status' => $status,
            'coach_id' => $session->coach_id,
            'walk_in_fee_sen' => $walkInFeeSen,
            'marked_by' => $this->coaches['Farid']->id,
        ]);

        if (in_array($status, ['present', 'late'], true)) {
            $now = now();
            AssessmentScore::insert(
                $this->skills->map(fn (Skill $skill): array => [
                    'attendance_id' => $attendance->id,
                    'skill_id' => $skill->id,
                    'score' => 3,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all(),
            );
        }

        return $attendance;
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
            'phone' => sprintf('013-%03d %04d', intdiv($this->parentSeq, 10000), $this->parentSeq % 10000),
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

    private function makeStudent(?int $parentId, string $name, bool $withIc = true): Student
    {
        $seq = ++$this->icSeq;

        return Student::create([
            'parent_id' => $parentId,
            'name' => $name,
            'ic_number' => $withIc ? '07'.str_pad((string) $seq, 10, '0', STR_PAD_LEFT) : null,
            'dob' => now()->subYears(8 + ($seq % 6))->subMonths($seq % 12)->toDateString(),
            'gender' => $seq % 2 === 0 ? 'male' : 'female',
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
        $names = ['Farid', 'Amir', 'Lena', 'Hafiz'];

        return $this->coaches[$names[$index % 4]]->id;
    }
}
