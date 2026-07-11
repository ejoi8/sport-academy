<?php

namespace App\Filament\Pages;

use App\Enums\Gender;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Student;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Coach-facing student console — the second tab of the focused coach shell. A read view (profile,
 * credits, attendance, assessments, session history) plus add/edit, all in the same .rt UI so a
 * coach never has to drop back into the admin panel. The admin StudentResource is left untouched.
 */
class Students extends Page
{
    protected string $view = 'filament.pages.students';

    // A distinct slug so this coach page doesn't collide with the admin StudentResource at
    // `app/students` (which would shadow this route and break the tab link).
    protected static ?string $slug = 'coach/students';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::UserGroup;

    protected static ?string $title = 'Students';

    protected static string|UnitEnum|null $navigationGroup = 'Training & Assessment';

    // Reached from the Run Training tab bar, so keep it out of the sidebar — otherwise admins see a
    // second "Students" entry beside the existing resource. The route still exists for the tab link.
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    // The shell renders its own app bar, so suppress Filament's default heading.
    public function getHeading(): string
    {
        return '';
    }

    // The open student's id, synced to `?student=` so a profile is deep-linkable/refresh-safe.
    #[Url(as: 'student', history: false)]
    public ?int $studentId = null;

    // The open enrolment's id, synced to `?enrolment=`. When set, the per-session report screen is
    // shown for that enrolment (a drill-down from the profile). Only the id ever reaches the URL.
    #[Url(as: 'enrolment', history: false)]
    public ?int $enrollmentId = null;

    public string $search = '';

    // How many roster rows to show; grows via "Load more" and resets when the search changes.
    public int $perPage = 40;

    // Add/edit sheet. `editingId === null` means we're creating a new student.
    public bool $editing = false;

    public ?int $editingId = null;

    public string $fName = '';

    public string $fIc = '';

    public string $fDob = '';

    public ?string $fGender = null;

    public string $fGuardianName = '';

    public string $fGuardianPhone = '';

    public string $fNotes = '';

    public bool $fActive = true;

    public function mount(): void
    {
        // A deep-linked enrolment resolves its own student so Back always reaches the profile;
        // a bogus/stale enrolment id is dropped silently.
        if ($this->enrollmentId) {
            $enrollment = Enrollment::find($this->enrollmentId);

            if ($enrollment) {
                $this->studentId = $enrollment->student_id;
            } else {
                $this->enrollmentId = null;
            }
        }

        // A `student` id only survives if it still exists — bogus/stale ids fall back to the list
        // silently (the coach just sees the roster), never an error page.
        if ($this->studentId && ! Student::whereKey($this->studentId)->exists()) {
            $this->studentId = null;
        }
    }

    /**
     * The searchable student list. Kept light (no per-row credit maths) to avoid an N+1 across the
     * roster — the full picture lives on the profile screen.
     *
     * @return array<int, array{id:int, name:string, age:?int, ic:?string, active:bool}>
     */
    /** The search-filtered roster query (active first), shared by the list and its count. */
    protected function studentQuery(): Builder
    {
        $term = str_replace(['\\', '%', '_'], '', trim($this->search));

        return Student::query()
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $where) => $where
                ->where('name', 'like', "%{$term}%")
                ->orWhere('ic_number', 'like', "%{$term}%")
                ->orWhere('guardian_name', 'like', "%{$term}%")))
            ->orderByDesc('is_active')
            ->orderBy('name');
    }

    #[Computed]
    public function results(): array
    {
        return $this->studentQuery()
            ->limit($this->perPage)
            ->get()
            ->map(fn (Student $student): array => [
                'id' => $student->id,
                'name' => $student->name,
                'age' => $student->age,
                'ic' => $student->ic_number,
                'active' => (bool) $student->is_active,
            ])
            ->all();
    }

    /** Total students matching the current search (ignores the page size). */
    #[Computed]
    public function matchingCount(): int
    {
        return $this->studentQuery()->count();
    }

    public function updatedSearch(): void
    {
        $this->perPage = 40; // start each new search from the top
    }

    public function loadMore(): void
    {
        $this->perPage += 40;
    }

    #[Computed]
    public function student(): ?Student
    {
        return $this->studentId ? Student::find($this->studentId) : null;
    }

    /**
     * Everything the profile screen renders, bundled so the (query-heavy) summaries compute once
     * per request rather than on every re-render.
     *
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function profile(): ?array
    {
        $student = $this->student;

        if (! $student) {
            return null;
        }

        $attendance = $student->attendanceCounts();

        return [
            'credit' => $student->creditSummary(),
            'carried' => $student->carriedCreditsCount(),
            'attendance' => $attendance,
            'attended' => (int) (($attendance['present'] ?? 0) + ($attendance['late'] ?? 0)),
            'assessment' => $student->assessmentSummary(),
            'history' => $student->sessionHistory(),
            'enrollments' => $student->enrollments()->with('offering.program')->latest()->get(),
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function genderOptions(): array
    {
        return collect(Gender::cases())->mapWithKeys(fn (Gender $gender): array => [$gender->value => $gender->getLabel()])->all();
    }

    public function openStudent(int $id): void
    {
        $this->studentId = $id;
    }

    public function back(): void
    {
        $this->studentId = null;
    }

    public function openEnrollment(int $id): void
    {
        $enrollment = Enrollment::find($id);

        if (! $enrollment) {
            return;
        }

        $this->studentId = $enrollment->student_id;
        $this->enrollmentId = $id;
    }

    public function backToStudent(): void
    {
        $this->enrollmentId = null;
    }

    #[Computed]
    public function enrollment(): ?Enrollment
    {
        return $this->enrollmentId ? Enrollment::with('offering.program')->find($this->enrollmentId) : null;
    }

    /**
     * The per-session report for the open enrolment: a credit/attendance headline, the skill
     * averages earned under it, and every session it delivered (newest first) with scores + notes.
     *
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function enrollmentReport(): ?array
    {
        $enrollment = $this->enrollment;

        if (! $enrollment) {
            return null;
        }

        $attendances = $enrollment->attendances()
            ->with(['trainingSession.offering.program', 'coach', 'scores.skill'])
            ->get()
            ->sortByDesc(fn (Attendance $attendance): string => (string) $attendance->trainingSession?->session_date)
            ->values();

        $sessions = $attendances->map(fn (Attendance $attendance): array => [
            'date' => $attendance->trainingSession?->session_date
                ? Carbon::parse($attendance->trainingSession->session_date)
                : null,
            'status' => $attendance->status->value,
            'coach' => $attendance->coach?->name,
            'note' => $attendance->note,
            'scores' => $attendance->scores
                ->sortBy(fn ($score): int => $score->skill?->sort_order ?? 0)
                ->map(fn ($score): array => ['skill' => $score->skill?->name, 'score' => $score->score])
                ->values()
                ->all(),
        ])->all();

        $skills = $attendances->flatMap->scores
            ->groupBy('skill_id')
            ->map(function ($scores): array {
                $skill = $scores->first()->skill;

                return [
                    'skill' => $skill?->name ?? '—',
                    'sort' => $skill?->sort_order ?? 0,
                    'count' => $scores->count(),
                    'average' => round((float) $scores->avg('score'), 1),
                ];
            })
            ->sortBy('sort')
            ->values()
            ->all();

        return [
            'sessions' => $sessions,
            'skills' => $skills,
            'attended' => $attendances->filter(fn (Attendance $attendance): bool => in_array($attendance->status->value, ['present', 'late'], true))->count(),
            'total_sessions' => $attendances->count(),
            'credits_used' => $enrollment->creditsUsed(),
            'credits_total' => (int) $enrollment->sessions_included,
        ];
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->editing = true;
    }

    public function startEdit(?int $id = null): void
    {
        $student = Student::find($id ?? $this->studentId);

        if (! $student) {
            return;
        }

        $this->editingId = $student->id;
        $this->fName = $student->name;
        $this->fIc = (string) $student->ic_number;
        $this->fDob = $student->dob?->toDateString() ?? '';
        $this->fGender = $student->gender?->value;
        $this->fGuardianName = (string) $student->guardian_name;
        $this->fGuardianPhone = (string) $student->guardian_phone;
        $this->fNotes = (string) $student->notes;
        $this->fActive = (bool) $student->is_active;
        $this->editing = true;
    }

    public function cancelForm(): void
    {
        $this->editing = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->reset('editingId', 'fName', 'fIc', 'fDob', 'fGender', 'fGuardianName', 'fGuardianPhone', 'fNotes');
        $this->fActive = true;
    }

    public function saveStudent(): void
    {
        // Creating/editing a student record is a staff action — defence in depth if page access widens.
        abort_unless((bool) Auth::user()?->hasAnyRole(['admin', 'coach', 'super_admin']), 403);

        $this->validate([
            'fName' => ['required', 'string', 'max:255'],
            'fIc' => ['nullable', 'string', 'max:50', Rule::unique('students', 'ic_number')->ignore($this->editingId)],
            'fDob' => ['nullable', 'date'],
            'fGender' => ['nullable', Rule::enum(Gender::class)],
            'fGuardianName' => ['nullable', 'string', 'max:255'],
            'fGuardianPhone' => ['nullable', 'string', 'max:50'],
            'fNotes' => ['nullable', 'string', 'max:2000'],
        ], attributes: [
            'fName' => 'name',
            'fIc' => 'IC number',
            'fDob' => 'date of birth',
            'fGender' => 'gender',
            'fGuardianName' => 'guardian name',
            'fGuardianPhone' => 'guardian phone',
            'fNotes' => 'notes',
        ]);

        $payload = [
            'name' => trim($this->fName),
            'ic_number' => trim($this->fIc) ?: null,
            'dob' => $this->fDob ?: null,
            'gender' => $this->fGender ?: null,
            'guardian_name' => trim($this->fGuardianName) ?: null,
            'guardian_phone' => trim($this->fGuardianPhone) ?: null,
            'notes' => trim($this->fNotes) ?: null,
            'is_active' => $this->fActive,
        ];

        $isNew = $this->editingId === null;

        if ($isNew) {
            $student = Student::create($payload);
            $this->studentId = $student->id; // drop straight into the new student's profile
        } else {
            Student::findOrFail($this->editingId)->update($payload);
        }

        $this->editing = false;
        $this->resetForm();
        $this->flushCaches();

        Notification::make()->success()->title($isNew ? 'Student added' : 'Student updated')->send();
    }

    public function toggleActive(): void
    {
        abort_unless((bool) Auth::user()?->hasAnyRole(['admin', 'coach', 'super_admin']), 403);

        $student = $this->student;

        if (! $student) {
            return;
        }

        $student->update(['is_active' => ! $student->is_active]);
        $this->flushCaches();

        Notification::make()->success()->title($student->is_active ? 'Marked active' : 'Marked inactive')->send();
    }

    public function deleteStudent(): void
    {
        abort_unless((bool) Auth::user()?->hasAnyRole(['admin', 'coach', 'super_admin']), 403);

        $student = $this->student;

        if (! $student) {
            return;
        }

        // A student with recorded sessions is history we don't destroy — mirror the model's own rule.
        if ($reason = $student->deletionBlockedReason()) {
            Notification::make()->warning()->title('Cannot delete')->body($reason)->send();

            return;
        }

        $student->delete();
        $this->studentId = null;
        $this->flushCaches();

        Notification::make()->success()->title('Student deleted')->send();
    }

    /**
     * Bust the computed caches so list/profile reflect the just-written change within this request.
     */
    protected function flushCaches(): void
    {
        unset($this->results, $this->matchingCount, $this->student, $this->profile, $this->enrollment, $this->enrollmentReport);
    }
}
