<?php

namespace App\Filament\Pages;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Attendance;
use App\Models\Offering;
use App\Models\Student;
use App\Models\TrainingSession;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use UnitEnum;

/**
 * The coach console's home screen — "my day" at a glance: a greeting, this week/month stats, and
 * the coach's own timeslots deep-linked into Run Training. Reuses the same queries as the coach
 * dashboard widgets (CoachStatsWidget / CoachTimeslotsWidget), re-dressed in the .rt shell.
 */
class CoachHome extends Page
{
    protected string $view = 'filament.pages.coach-home';

    protected static ?string $slug = 'coach/home';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::Home;

    protected static ?string $title = 'Home';

    protected static string|UnitEnum|null $navigationGroup = 'Training & Assessment';

    protected static ?int $navigationSort = -1;

    // The console entry point in the sidebar — coaches only, so admins don't get a personal coach
    // home with no data. The route still exists for everyone (the Home tab links to it).
    public static function shouldRegisterNavigation(): bool
    {
        return (bool) Auth::user()?->hasAnyRole(['coach', 'super_admin']);
    }

    // The shell renders its own app bar, so suppress Filament's default heading.
    public function getHeading(): string
    {
        return '';
    }

    #[Computed]
    public function coachName(): string
    {
        return Str::of((string) Auth::user()?->name)->trim()->explode(' ')->first() ?: 'Coach';
    }

    /**
     * This-week / this-month headline numbers for the signed-in coach.
     *
     * @return array{sessions_week:int, to_assess:int, attendance:string}
     */
    #[Computed]
    public function stats(): array
    {
        $coachId = Auth::id();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $sessionsWeek = TrainingSession::where('coach_id', $coachId)
            ->whereBetween('session_date', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()])
            ->count();

        $toAssess = Student::whereHas('enrollments', fn (Builder $query) => $query
            ->where('status', EnrollmentStatus::Active)
            ->whereHas('offering', fn (Builder $offering) => $offering
                ->where('default_coach_id', $coachId)
                ->where('period', now()->format('Y-m'))))
            ->count();

        $total = Attendance::where('coach_id', $coachId)->whereBetween('created_at', [$monthStart, $monthEnd])->count();
        $present = Attendance::where('coach_id', $coachId)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->whereIn('status', [AttendanceStatus::Present, AttendanceStatus::Late])
            ->count();

        return [
            'sessions_week' => $sessionsWeek,
            'to_assess' => $toAssess,
            'attendance' => $total > 0 ? round($present / $total * 100).'%' : '—',
        ];
    }

    /**
     * The coach's open timeslots this month, each deep-linked to its nearest session in Run Training
     * so a tap lands on an already-open card. Flags the ones that run today.
     *
     * @return array<int, array{id:int, program:string, schedule:string, enrolled:int, capacity:int, today:bool, url:string}>
     */
    #[Computed]
    public function timeslots(): array
    {
        $todayWeekday = now()->dayOfWeekIso;

        return Offering::query()
            ->where('default_coach_id', Auth::id())
            ->where('period', now()->format('Y-m'))
            ->where('is_open', true)
            ->with('program')
            ->withCount(['enrollments as active_count' => fn (Builder $query) => $query->where('status', EnrollmentStatus::Active)])
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get()
            ->map(function (Offering $offering) use ($todayWeekday): array {
                $date = $offering->nearestOccurrence();

                return [
                    'id' => $offering->id,
                    'program' => $offering->program?->name ?? 'Session',
                    'schedule' => $offering->scheduleLabel(),
                    'enrolled' => (int) $offering->active_count,
                    'capacity' => (int) $offering->capacity,
                    'today' => (int) $offering->weekday === $todayWeekday,
                    'url' => $date
                        ? RunTraining::getUrl(['date' => $date->toDateString(), 'session' => $offering->id])
                        : RunTraining::getUrl(),
                ];
            })
            ->all();
    }
}
