<?php

namespace App\Filament\Pages;

use App\Enums\AttendanceStatus;
use App\Enums\ParticipantType;
use App\Enums\ScheduleType;
use App\Models\AssessmentScore;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\Skill;
use App\Models\Student;
use App\Models\TrainingSession;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Spatie\Permission\Models\Role;
use UnitEnum;

class RunTraining extends Page
{
    protected string $view = 'filament.pages.run-training';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Training & Assessment';

    protected static ?string $title = 'Run Training';

    public ?int $offeringId = null;

    public string $date = '';

    public string $period = '';

    /** @var array<string, array<string, mixed>> */
    public array $roster = [];

    public ?string $expandedKey = null;

    public int $walkInFeeSen = 0;

    // The timeslot's head coach — the blank-default for each student.
    public ?int $headCoachId = null;

    // Selection for the "assign all to…" bulk action.
    public ?int $bulkCoachId = null;

    public bool $dirty = false;

    public bool $savedSessionExists = false;

    // Whether a session is open for the current date (loaded / being recorded). False shows the
    // "Start session" prompt, because nothing has been recorded for this date yet.
    public bool $started = false;

    // Add-participant panel state.
    public bool $adding = false;

    public string $search = '';

    public string $newName = '';

    public string $newPhone = '';

    public string $newIc = '';

    public int $rowSeq = 0;

    // Add-coach panel state.
    public bool $addingCoach = false;

    public string $newCoachName = '';

    public string $newCoachEmail = '';

    public function mount(): void
    {
        $this->period = $this->defaultPeriod();
        $this->selectMonthOffering();
        $this->loadRoster();
    }

    public function updatedPeriod(): void
    {
        $this->selectMonthOffering();
        $this->loadRoster();
    }

    /**
     * Pick the first timeslot of the selected month and snap the date to it.
     */
    protected function selectMonthOffering(): void
    {
        $offering = Offering::query()
            ->where('is_open', true)
            ->where('period', $this->period)
            ->orderBy('program_id')
            ->orderBy('start_time')
            ->first();

        $this->offeringId = $offering?->id;
        $this->date = $offering ? $this->defaultDateForOffering($offering) : today()->toDateString();
    }

    protected function defaultPeriod(): string
    {
        $current = now()->format('Y-m');

        if (Offering::query()->where('is_open', true)->where('period', $current)->exists()) {
            return $current;
        }

        return Offering::query()->where('is_open', true)->max('period') ?? $current;
    }

    public function updatedOfferingId(): void
    {
        // Snap the date to the chosen timeslot's next (or most recent) occurrence.
        if ($this->offeringId && ($offering = Offering::find($this->offeringId))) {
            $this->date = $this->defaultDateForOffering($offering);
        }

        $this->loadRoster();
    }

    public function goToday(): void
    {
        $this->date = today()->toDateString();
        $this->loadRoster();
    }

    /**
     * The most relevant date for a timeslot: its specific date (one-off), or the
     * next occurrence of its weekday in its month — falling back to the last
     * occurrence when the month has already passed (so old months land on real sessions).
     */
    protected function defaultDateForOffering(Offering $offering): string
    {
        if ($offering->schedule_type === ScheduleType::OneOff) {
            return $offering->specific_date?->toDateString() ?? today()->toDateString();
        }

        if (! $offering->weekday) {
            return today()->toDateString();
        }

        $monthStart = Carbon::parse($offering->period.'-01')->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $dates = collect();
        $cursor = $monthStart->copy();
        while ($cursor->lte($monthEnd)) {
            if ($cursor->dayOfWeekIso === $offering->weekday) {
                $dates->push($cursor->copy());
            }
            $cursor->addDay();
        }

        if ($dates->isEmpty()) {
            return today()->toDateString();
        }

        $upcoming = $dates->first(fn (Carbon $date): bool => $date->gte(today()));

        if ($upcoming) {
            return $upcoming->toDateString();
        }

        // Past month: land on the most recent occurrence that actually has a saved session
        // (so a month with a 5th, session-less occurrence still opens on real history).
        $lastSaved = TrainingSession::query()
            ->where('offering_id', $offering->id)
            ->orderByDesc('session_date')
            ->value('session_date');

        return $lastSaved ?? $dates->last()->toDateString();
    }

    public function updatedDate(): void
    {
        if (blank($this->date)) {
            $this->date = today()->toDateString();
        }

        $this->loadRoster();
    }

    public function discard(): void
    {
        // Revert unsaved edits back to the saved state for the current timeslot + date.
        $this->loadRoster();
    }

    public function updatedRoster(): void
    {
        // Fires when a note or per-student coach (wire:model) changes.
        $this->dirty = true;
    }

    public function shiftDay(int $days): void
    {
        $this->date = Carbon::parse($this->date)->addDays($days)->toDateString();
        $this->loadRoster();
    }

    public function loadRoster(): void
    {
        $this->expandedKey = null;
        $this->adding = false;
        $this->roster = [];
        $this->dirty = false;
        $this->savedSessionExists = false;
        $this->started = false;

        if (! $this->offeringId) {
            $this->headCoachId = null;
            $this->bulkCoachId = null;

            return;
        }

        $offering = Offering::with('program')->find($this->offeringId);
        $this->walkInFeeSen = (int) ($offering?->program?->walk_in_fee_sen ?? 0);
        $this->headCoachId = $offering?->default_coach_id;
        $this->bulkCoachId = $this->headCoachId;

        $session = TrainingSession::query()
            ->where('offering_id', $this->offeringId)
            ->where('session_date', $this->date)
            ->with(['attendances.student', 'attendances.scores'])
            ->first();

        // Nothing recorded for this date yet — leave the roster empty so the page shows the
        // "Start session" prompt instead of a phantom roster.
        if (! $session) {
            return;
        }

        $this->savedSessionExists = true;
        $this->started = true;

        $this->loadEnrolledRoster();
        $this->hydrateFromSession($session);
    }

    /**
     * Open a fresh session for the current timeslot + date by pulling in the enrolled roster so
     * the coach can record it. A saved session is auto-opened by loadRoster() instead.
     */
    public function startSession(): void
    {
        if (! $this->offeringId || blank($this->date) || $this->started) {
            return;
        }

        $this->roster = [];
        $this->started = true;

        $this->loadEnrolledRoster();
    }

    /**
     * Pre-fill the roster with this timeslot's enrolled subscribers (present by default), each
     * carrying its enrolment's saved credit usage.
     */
    protected function loadEnrolledRoster(): void
    {
        Enrollment::query()
            ->where('offering_id', $this->offeringId)
            ->whereIn('status', ['active', 'pending', 'overdue'])
            ->withCount(['attendances as used_credits' => fn ($query) => $query->whereIn('status', Enrollment::CREDIT_CONSUMING_STATUSES)])
            ->with('student')
            ->get()
            ->each(function (Enrollment $enrollment): void {
                if (! $enrollment->student) {
                    return;
                }

                $this->roster['s'.$enrollment->student_id] = $this->makeRow(
                    $enrollment->student,
                    ParticipantType::Enrolled->value,
                    [
                        'payment_status' => $enrollment->status->value,
                        'credits_used' => (int) $enrollment->used_credits,
                        'credits_total' => $enrollment->sessions_included,
                        'enrollment_id' => $enrollment->id,
                    ],
                );
            });
    }

    /**
     * Overlay a saved session's attendance, coach, notes and scores onto the roster.
     */
    protected function hydrateFromSession(TrainingSession $session): void
    {
        foreach ($session->attendances as $attendance) {
            $key = 's'.$attendance->student_id;

            if (! isset($this->roster[$key])) {
                if (! $attendance->student) {
                    continue;
                }
                $this->roster[$key] = $this->makeRow(
                    $attendance->student,
                    $attendance->participant_type->value,
                    [
                        'fee_sen' => $attendance->walk_in_fee_sen,
                        'enrollment_id' => $attendance->enrollment_id,
                    ],
                );
            }

            $this->roster[$key]['type'] = $attendance->participant_type->value;
            $this->roster[$key]['status'] = $attendance->status->value;
            $this->roster[$key]['coach_id'] = $attendance->coach_id;
            $this->roster[$key]['note'] = $attendance->note ?? '';
            $this->roster[$key]['enrollment_id'] = $attendance->enrollment_id;

            foreach ($attendance->scores as $score) {
                $this->roster[$key]['scores'][$score->skill_id] = $score->score;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function makeRow(Student $student, string $type, array $meta = []): array
    {
        return array_merge([
            'student_id' => $student->id,
            'name' => $student->name,
            'type' => $type,
            'payment_status' => null,   // enrolment status: active|pending|overdue
            'credits_used' => null,     // credits already consumed on the source enrolment
            'credits_total' => null,    // the source enrolment's session count
            'enrollment_id' => null,    // which enrolment this row consumes a credit from
            'coach_id' => $this->headCoachId,
            'status' => AttendanceStatus::Present->value,
            'scores' => $this->skills->mapWithKeys(fn (Skill $skill): array => [$skill->id => null])->all(),
            'note' => '',
            'fee_sen' => null,
            'phone' => null,
            'ic' => $student->ic_number,
        ], $meta);
    }

    public function toggle(string $key): void
    {
        $this->expandedKey = $this->expandedKey === $key ? null : $key;
    }

    public function setStatus(string $key, string $status): void
    {
        if (isset($this->roster[$key])) {
            $this->roster[$key]['status'] = $status;
            $this->dirty = true;
        }
    }

    public function setScore(string $key, int $skillId, int $score): void
    {
        if (! isset($this->roster[$key])) {
            return;
        }

        $current = $this->roster[$key]['scores'][$skillId] ?? null;
        $this->roster[$key]['scores'][$skillId] = $current === $score ? null : $score;
        $this->dirty = true;
    }

    public function assignAll(): void
    {
        $coachId = $this->bulkCoachId ?: null;

        foreach ($this->roster as $key => $row) {
            $this->roster[$key]['coach_id'] = $coachId;
        }

        $this->dirty = true;
    }

    public function startAdd(): void
    {
        if (! $this->offeringId) {
            return;
        }

        $this->adding = true;
        $this->reset('search', 'newName', 'newPhone', 'newIc');
    }

    public function cancelAdd(): void
    {
        $this->adding = false;
        $this->reset('search', 'newName', 'newPhone', 'newIc');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function results(): array
    {
        $term = $this->sanitiseSearch($this->search);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $onRoster = collect($this->roster)->pluck('student_id')->filter()->all();

        return Student::query()
            ->where(fn ($query) => $query->where('name', 'like', "%{$term}%")->orWhere('ic_number', 'like', "%{$term}%"))
            ->whereNotIn('id', $onRoster)
            ->limit(10)
            ->get()
            ->map(function (Student $student): array {
                // Make-up only if they still hold a live credit; otherwise a paying walk-in.
                $makeUp = $student->liveCreditEnrollment();

                return [
                    'id' => $student->id,
                    'name' => $student->name,
                    'ic' => $student->ic_number,
                    'age' => $student->age,
                    'program' => $makeUp?->offering?->program?->name,
                    'type' => $makeUp ? ParticipantType::MakeUp->value : ParticipantType::WalkIn->value,
                    'fee_sen' => $makeUp ? null : $this->walkInFeeSen,
                    'credits_left' => $makeUp?->creditsRemaining(),
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function suggestion(): ?array
    {
        $name = $this->sanitiseSearch($this->newName);

        if (mb_strlen($name) < 2) {
            return null;
        }

        $onRoster = collect($this->roster)->pluck('student_id')->filter()->all();

        $match = Student::query()
            ->when(
                trim($this->newIc) !== '',
                fn ($query) => $query->where('ic_number', trim($this->newIc)),
                fn ($query) => $query->where('name', 'like', "%{$name}%"),
            )
            ->whereNotIn('id', $onRoster)
            ->first();

        return $match ? ['id' => $match->id, 'name' => $match->name] : null;
    }

    public function addExisting(int $studentId): void
    {
        $key = 's'.$studentId;

        if (isset($this->roster[$key])) {
            $this->expandedKey = $key;
            $this->cancelAdd();

            return;
        }

        $student = Student::find($studentId);

        if (! $student) {
            return;
        }

        // Make-up only if they still hold a live credit; otherwise a paying walk-in.
        $makeUp = $student->liveCreditEnrollment();

        $this->roster[$key] = $this->makeRow(
            $student,
            $makeUp ? ParticipantType::MakeUp->value : ParticipantType::WalkIn->value,
            [
                'fee_sen' => $makeUp ? null : $this->walkInFeeSen,
                'enrollment_id' => $makeUp?->id,
                'credits_used' => $makeUp?->creditsUsed(),
                'credits_total' => $makeUp?->sessions_included,
            ],
        );

        $this->expandedKey = $key;
        $this->dirty = true;
        $this->cancelAdd();
    }

    public function addNewWalkIn(): void
    {
        $this->validate(['newName' => ['required', 'string', 'max:255']]);

        if (trim($this->newIc) !== '') {
            $existing = Student::where('ic_number', trim($this->newIc))->first();
            if ($existing) {
                $this->addExisting($existing->id);

                return;
            }
        }

        $key = 'n'.(++$this->rowSeq);

        $this->roster[$key] = [
            'student_id' => null,
            'name' => trim($this->newName),
            'type' => ParticipantType::WalkIn->value,
            'payment_status' => null,
            'credits_used' => null,
            'credits_total' => null,
            'enrollment_id' => null,
            'coach_id' => $this->headCoachId,
            'status' => AttendanceStatus::Present->value,
            'scores' => $this->skills->mapWithKeys(fn (Skill $skill): array => [$skill->id => null])->all(),
            'note' => '',
            'fee_sen' => $this->walkInFeeSen,
            'phone' => trim($this->newPhone) ?: null,
            'ic' => trim($this->newIc) ?: null,
        ];

        $this->expandedKey = $key;
        $this->dirty = true;
        $this->cancelAdd();
    }

    public function removeRow(string $key): void
    {
        unset($this->roster[$key]);

        if ($this->expandedKey === $key) {
            $this->expandedKey = null;
        }

        $this->dirty = true;
    }

    public function startAddCoach(): void
    {
        $this->addingCoach = true;
        $this->reset('newCoachName', 'newCoachEmail');
    }

    public function cancelAddCoach(): void
    {
        $this->addingCoach = false;
        $this->reset('newCoachName', 'newCoachEmail');
    }

    public function saveCoach(): void
    {
        $this->validate([
            'newCoachName' => ['required', 'string', 'max:255'],
            'newCoachEmail' => ['nullable', 'email', 'unique:users,email'],
        ]);

        $email = trim($this->newCoachEmail);

        if ($email === '') {
            $base = Str::slug($this->newCoachName) ?: 'coach';
            do {
                $email = $base.'-'.Str::lower(Str::random(5)).'@academy.test';
            } while (User::where('email', $email)->exists());
        }

        $coach = User::create([
            'name' => trim($this->newCoachName),
            'email' => $email,
            'password' => Hash::make(Str::random(16)),
            'email_verified_at' => now(),
        ]);

        $coach->assignRole(Role::firstOrCreate(['name' => 'coach', 'guard_name' => 'web']));

        unset($this->coachOptions); // bust the computed cache so the new coach appears in dropdowns

        $this->addingCoach = false;
        $this->reset('newCoachName', 'newCoachEmail');

        Notification::make()->success()->title('Coach added')->send();
    }

    public function save(): void
    {
        if (! $this->offeringId) {
            Notification::make()->warning()->title('Pick a program / timeslot first')->send();

            return;
        }

        if (blank($this->date)) {
            Notification::make()->warning()->title('Pick a date first')->send();

            return;
        }

        if ($this->roster === []) {
            Notification::make()->warning()->title('No players on the roster to record')->send();

            return;
        }

        DB::transaction(function (): void {
            $session = TrainingSession::firstOrCreate(
                ['offering_id' => $this->offeringId, 'session_date' => $this->date],
                ['coach_id' => $this->headCoachId, 'created_by' => Auth::id()],
            );

            // Keep the session's lead coach aligned with the timeslot's head coach.
            if ($session->coach_id !== $this->headCoachId) {
                $session->update(['coach_id' => $this->headCoachId]);
            }

            // Write one attendance row per roster entry, remembering each id.
            $attendanceIdByStudent = [];

            foreach ($this->roster as $key => $row) {
                $studentId = $this->resolveStudentId($key, $row);
                $attendance = $this->writeAttendance($session, $studentId, $row);
                $attendanceIdByStudent[$studentId] = $attendance->id;
            }

            // Replace this session's rubric scores in just two statements — one delete then
            // one bulk insert — instead of a query per player per skill.
            AssessmentScore::query()
                ->whereIn('attendance_id', array_values($attendanceIdByStudent))
                ->delete();

            $scoreRows = $this->buildScoreRows($attendanceIdByStudent);

            if ($scoreRows !== []) {
                AssessmentScore::insert($scoreRows);
            }

            // Remove anyone no longer on the roster (their scores cascade away).
            Attendance::query()
                ->where('training_session_id', $session->id)
                ->whereNotIn('student_id', array_keys($attendanceIdByStudent))
                ->delete();
        });

        $this->dirty = false;
        $this->savedSessionExists = true;

        Notification::make()->success()->title('Training session saved')->send();
    }

    /**
     * Return the student id for a roster row, creating a new walk-in student the
     * first time it is saved (reusing an existing student that matches the IC).
     *
     * @param  array<string, mixed>  $row
     */
    protected function resolveStudentId(string $key, array $row): int
    {
        if (! empty($row['student_id'])) {
            return (int) $row['student_id'];
        }

        $ic = trim((string) ($row['ic'] ?? ''));
        $existing = $ic !== '' ? Student::where('ic_number', $ic)->first() : null;

        $studentId = $existing?->id ?? Student::create([
            'name' => $row['name'],
            'guardian_phone' => $row['phone'] ?? null,
            'ic_number' => $ic !== '' ? $ic : null,
            'is_active' => true,
        ])->id;

        // Remember it so a second save does not create the student again.
        $this->roster[$key]['student_id'] = $studentId;

        return (int) $studentId;
    }

    /**
     * Create or update the attendance row for one player in this session.
     *
     * @param  array<string, mixed>  $row
     */
    protected function writeAttendance(TrainingSession $session, int $studentId, array $row): Attendance
    {
        $isWalkIn = $row['type'] === ParticipantType::WalkIn->value;
        $isAbsent = $this->isAbsentStatus($row['status']);

        $attendance = Attendance::firstOrNew([
            'training_session_id' => $session->id,
            'student_id' => $studentId,
        ]);

        $attendance->fill([
            'participant_type' => $row['type'],
            'status' => $row['status'],
            'coach_id' => ! empty($row['coach_id']) ? (int) $row['coach_id'] : null,
            // A walk-in only pays a fee when they actually attended.
            'walk_in_fee_sen' => ($isWalkIn && ! $isAbsent) ? ($row['fee_sen'] ?? null) : null,
            'note' => filled($row['note'] ?? null) ? $row['note'] : null,
            'marked_by' => Auth::id(),
            // The credit pool this consumes: own enrolment (enrolled), a live-credit pool
            // (make-up) or none (walk-in). A pool already resolved on a prior save is kept.
            'enrollment_id' => $isWalkIn ? null : ($attendance->enrollment_id ?? $this->resolveEnrollmentId($studentId, $row['type'])),
        ]);

        $attendance->save();

        return $attendance;
    }

    /**
     * The enrolment whose session credit an attendance consumes: the student's enrolment in
     * this timeslot (enrolled), their oldest live-credit pool (make-up), or none (walk-in).
     */
    protected function resolveEnrollmentId(int $studentId, string $type): ?int
    {
        if ($type === ParticipantType::Enrolled->value) {
            return Enrollment::query()
                ->where('offering_id', $this->offeringId)
                ->where('student_id', $studentId)
                ->value('id');
        }

        if ($type === ParticipantType::MakeUp->value) {
            return Student::find($studentId)?->liveCreditEnrollment()?->id;
        }

        return null;
    }

    /**
     * Build the rubric score rows to bulk-insert. Absent players and cleared
     * pills contribute nothing, so they are simply left out.
     *
     * @param  array<int, int>  $attendanceIdByStudent  student id => attendance id
     * @return array<int, array<string, mixed>>
     */
    protected function buildScoreRows(array $attendanceIdByStudent): array
    {
        $now = now();
        $rows = [];

        foreach ($this->roster as $row) {
            if ($this->isAbsentStatus($row['status'])) {
                continue;
            }

            $attendanceId = $attendanceIdByStudent[$row['student_id']] ?? null;

            if (! $attendanceId) {
                continue;
            }

            foreach ($row['scores'] as $skillId => $score) {
                if (is_null($score)) {
                    continue;
                }

                $rows[] = [
                    'attendance_id' => $attendanceId,
                    'skill_id' => (int) $skillId,
                    'score' => (int) $score,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        return $rows;
    }

    protected function isAbsentStatus(string $status): bool
    {
        return in_array($status, [AttendanceStatus::Absent->value, AttendanceStatus::Excused->value], true);
    }

    public function deleteSession(): void
    {
        $session = TrainingSession::query()
            ->where('offering_id', $this->offeringId)
            ->where('session_date', $this->date)
            ->first();

        if (! $session) {
            return;
        }

        $session->delete();

        $this->loadRoster();

        Notification::make()->success()->title('Session deleted')->send();
    }

    public function summary(): string
    {
        $players = count($this->roster);
        $present = collect($this->roster)->where('status', AttendanceStatus::Present->value)->count();
        $late = collect($this->roster)->where('status', AttendanceStatus::Late->value)->count();
        $total = max($this->skills->count(), 1);
        $fullyScored = collect($this->roster)
            ->filter(fn (array $row): bool => collect($row['scores'])->filter(fn ($score) => ! is_null($score))->count() === $total)
            ->count();

        return "{$players} players · {$present} present · {$late} late · {$fullyScored} fully scored";
    }

    protected function sanitiseSearch(string $value): string
    {
        return str_replace(['\\', '%', '_'], '', trim($value));
    }

    /**
     * @return Collection<int, Skill>
     */
    #[Computed]
    public function skills(): Collection
    {
        return Skill::query()->active()->orderBy('sort_order')->get();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function offeringOptions(): array
    {
        return Offering::query()
            ->where('is_open', true)
            ->where('period', $this->period)
            ->with('program')
            ->orderBy('program_id')
            ->orderBy('start_time')
            ->get()
            ->mapWithKeys(fn (Offering $offering): array => [$offering->id => $offering->label()])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function periodOptions(): array
    {
        return Offering::query()
            ->where('is_open', true)
            ->orderByDesc('period')
            ->pluck('period')
            ->unique()
            ->mapWithKeys(fn (string $period): array => [$period => Carbon::parse($period.'-01')->format('M Y')])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function coachOptions(): array
    {
        // Coaches, plus any coach currently referenced (head coach / a saved row) so their name
        // still renders in the dropdown/label even if they no longer hold the coach role.
        $referenced = collect($this->roster)->pluck('coach_id')->push($this->headCoachId)->filter()->unique()->all();

        return User::query()
            ->where(function ($query) use ($referenced) {
                $query->whereHas('roles', fn ($roles) => $roles->where('name', 'coach'));

                if ($referenced !== []) {
                    $query->orWhereIn('id', $referenced);
                }
            })
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
