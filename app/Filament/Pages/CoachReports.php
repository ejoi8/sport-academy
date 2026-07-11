<?php

namespace App\Filament\Pages;

use App\Models\AssessmentScore;
use App\Support\Reporting\AttendanceSummary;
use App\Support\Reporting\ProgressSummary;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use UnitEnum;

/**
 * The coach console's Reports tab — the signed-in coach's own delivery and outcomes: this month's
 * attendance, a 6-month score trend, and per-programme skill progress. Reuses the same summary
 * builders as the admin reports (AttendanceSummary / ProgressSummary), scoped to the coach.
 */
class CoachReports extends Page
{
    protected string $view = 'filament.pages.coach-reports';

    protected static ?string $slug = 'coach/reports';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::ChartBar;

    protected static ?string $title = 'Reports';

    protected static string|UnitEnum|null $navigationGroup = 'Training & Assessment';

    // Reached via the console tab bar; keep it out of the sidebar for non-coaches.
    public static function shouldRegisterNavigation(): bool
    {
        return (bool) Auth::user()?->hasAnyRole(['coach', 'super_admin']);
    }

    public function getHeading(): string
    {
        return '';
    }

    public function periodLabel(): string
    {
        return now()->format('F Y');
    }

    /**
     * This month's attendance & delivery for the signed-in coach.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function attendance(): array
    {
        return AttendanceSummary::for(now()->format('Y-m'), (int) Auth::id());
    }

    /**
     * All-time per-programme skill averages for the coach's own assessments.
     *
     * @return array<string, array{skills:array<int, array{skill:string, count:int, average:float}>, total_scores:int, overall_average:float}>
     */
    #[Computed]
    public function progress(): array
    {
        return ProgressSummary::build((int) Auth::id())['by_program'];
    }

    /**
     * Average assessment score for each of the last six months (0 = no scores that month).
     *
     * @return array<int, array{label:string, avg:float}>
     */
    #[Computed]
    public function trend(): array
    {
        $coachId = Auth::id();
        $out = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = now()->startOfMonth()->subMonths($i);

            $avg = AssessmentScore::whereHas('attendance', fn (Builder $query) => $query
                ->where('coach_id', $coachId)
                ->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()]))
                ->avg('score');

            $out[] = ['label' => $month->format('M'), 'avg' => $avg ? round((float) $avg, 1) : 0.0];
        }

        return $out;
    }

    /**
     * All-time overall average score across the coach's assessments — null if none recorded yet.
     */
    #[Computed]
    public function overallAverage(): ?float
    {
        $avg = AssessmentScore::whereHas('attendance', fn (Builder $query) => $query->where('coach_id', Auth::id()))->avg('score');

        return $avg ? round((float) $avg, 1) : null;
    }
}
