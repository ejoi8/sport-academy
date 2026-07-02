<?php

namespace App\Filament\Widgets;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Models\AssessmentScore;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\TrainingSession;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class CoachStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['coach', 'super_admin']) ?? false;
    }

    protected function getStats(): array
    {
        $coachId = auth()->id();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $sessionsThisWeek = TrainingSession::where('coach_id', $coachId)
            ->whereBetween('session_date', [
                now()->startOfWeek()->toDateString(),
                now()->endOfWeek()->toDateString(),
            ])
            ->count();

        $studentsToAssess = Student::whereHas('enrollments', fn (Builder $q) => $q
            ->where('status', EnrollmentStatus::Active)
            ->whereHas('offering', fn (Builder $o) => $o
                ->where('default_coach_id', $coachId)
                ->where('period', now()->format('Y-m'))))
            ->count();

        $assessmentsRecorded = AssessmentScore::whereHas('attendance', fn (Builder $q) => $q
            ->where('coach_id', $coachId)
            ->whereBetween('created_at', [$monthStart, $monthEnd]))
            ->count();

        $total = Attendance::where('coach_id', $coachId)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->count();

        $present = Attendance::where('coach_id', $coachId)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->whereIn('status', [AttendanceStatus::Present, AttendanceStatus::Late])
            ->count();

        $attendanceRate = $total > 0 ? round($present / $total * 100).'%' : '—';

        return [
            Stat::make('Sessions this week', $sessionsThisWeek),
            Stat::make('Students to assess', $studentsToAssess)
                ->description('across your timeslots'),
            Stat::make('Assessments recorded', $assessmentsRecorded)
                ->description(now()->format('F')),
            Stat::make('Attendance rate', $attendanceRate)
                ->color('success'),
        ];
    }
}
