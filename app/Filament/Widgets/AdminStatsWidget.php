<?php

namespace App\Filament\Widgets;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\Student;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class AdminStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'super_admin']) ?? false;
    }

    protected function getStats(): array
    {
        $activeEnrolments = Enrollment::where('status', EnrollmentStatus::Active)->count();

        $billedThisMonth = Enrollment::where('status', EnrollmentStatus::Active)
            ->whereHas('offering', fn (Builder $q) => $q->where('period', now()->format('Y-m')))
            ->sum('price_sen');

        $overdueCount = Enrollment::where('status', EnrollmentStatus::Overdue)->count();
        $overdueSum = Enrollment::where('status', EnrollmentStatus::Overdue)->sum('price_sen');

        $activeStudents = Student::whereHas('enrollments', fn (Builder $q) => $q->where('status', EnrollmentStatus::Active))->count();

        return [
            Stat::make('Active enrolments', $activeEnrolments)
                ->color('primary'),

            Stat::make('Billed this month', 'RM '.number_format($billedThisMonth / 100, 2))
                ->description('Billed for '.now()->format('F'))
                ->color('success'),

            Stat::make('Overdue', 'RM '.number_format($overdueSum / 100, 2))
                ->description($overdueCount.' enrolments at risk')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),

            Stat::make('Active students', $activeStudents)
                ->color('primary'),
        ];
    }
}
