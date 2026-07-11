<?php

namespace App\Filament\Pages;

use App\Support\Reporting\AttendanceSummary;
use App\Support\Reporting\CoachMetrics;
use App\Support\Reporting\ProgressSummary;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * The coach console's full report (reached from Home) — the signed-in coach's own delivery and
 * outcomes over a chosen window: attendance, a score trend, per-programme attendance and all-time
 * skill progress. The window is flexible (this month, a year, a custom range, …) and drives every
 * section. Reuses the same summary builders as the admin reports, scoped to the coach + window.
 */
class CoachReports extends Page
{
    protected string $view = 'filament.pages.coach-reports';

    protected static ?string $slug = 'coach/reports';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::ChartBar;

    protected static ?string $title = 'Reports';

    protected static string|UnitEnum|null $navigationGroup = 'Training & Assessment';

    /** The chosen reporting window preset, synced to the URL so a report is shareable/refresh-safe. */
    #[Url(as: 'range', history: false)]
    public string $range = 'this-month';

    // Custom-range bounds (only used when range = 'custom'), yyyy-mm-dd.
    #[Url(as: 'from', history: false, except: '')]
    public string $from = '';

    #[Url(as: 'to', history: false, except: '')]
    public string $to = '';

    // Reached via Home's "See full report"; keep it out of the sidebar for non-coaches.
    public static function shouldRegisterNavigation(): bool
    {
        return (bool) Auth::user()?->hasAnyRole(['coach', 'super_admin']);
    }

    public function getHeading(): string
    {
        return '';
    }

    /** The presets offered in the picker (value => label). */
    public function rangeOptions(): array
    {
        return [
            'this-month' => 'This month',
            'last-month' => 'Last month',
            'last-3' => 'Last 3 months',
            'last-6' => 'Last 6 months',
            'this-year' => 'This year',
            'last-12' => 'Last 12 months',
            'all' => 'All time',
            'custom' => 'Custom range…',
        ];
    }

    /**
     * Resolve the chosen preset (or custom dates) into an inclusive [from, to] window + a label.
     *
     * @return array{from:Carbon, to:Carbon, label:string}
     */
    #[Computed]
    public function window(): array
    {
        $today = today();

        [$from, $to] = match ($this->range) {
            'last-month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            'last-3' => [$today->copy()->subMonthsNoOverflow(2)->startOfMonth(), $today->copy()->endOfMonth()],
            'last-6' => [$today->copy()->subMonthsNoOverflow(5)->startOfMonth(), $today->copy()->endOfMonth()],
            'this-year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            'last-12' => [$today->copy()->subMonthsNoOverflow(11)->startOfMonth(), $today->copy()->endOfMonth()],
            'all' => [$today->copy()->subYears(20)->startOfYear(), $today->copy()->endOfDay()],
            'custom' => $this->customWindow($today),
            default => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
        };

        return ['from' => $from, 'to' => $to, 'label' => $this->label($from, $to)];
    }

    /**
     * @return array{0:Carbon, 1:Carbon}
     */
    protected function customWindow(Carbon $today): array
    {
        try {
            $from = $this->from !== '' ? Carbon::parse($this->from)->startOfDay() : $today->copy()->startOfMonth();
        } catch (\Throwable) {
            $from = $today->copy()->startOfMonth();
        }

        try {
            $to = $this->to !== '' ? Carbon::parse($this->to)->endOfDay() : $today->copy()->endOfDay();
        } catch (\Throwable) {
            $to = $today->copy()->endOfDay();
        }

        return $to->lt($from) ? [$to->copy()->startOfDay(), $from->copy()->endOfDay()] : [$from, $to];
    }

    protected function label(Carbon $from, Carbon $to): string
    {
        if ($from->isSameMonth($to) && $from->isSameDay($from->copy()->startOfMonth()) && $to->isSameDay($to->copy()->endOfMonth())) {
            return $from->format('F Y');
        }

        if ($from->isSameDay($from->copy()->startOfYear()) && $to->isSameDay($to->copy()->endOfYear()) && $from->year === $to->year) {
            return (string) $from->year;
        }

        return $this->range === 'all' ? 'All time' : $from->format('j M Y').' – '.$to->format('j M Y');
    }

    public function periodLabel(): string
    {
        return $this->window['label'];
    }

    /**
     * Attendance & delivery for the chosen window.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function attendance(): array
    {
        return AttendanceSummary::forRange($this->window['from'], $this->window['to'], (int) Auth::id());
    }

    /**
     * Per-programme skill averages for the coach's assessments within the window.
     *
     * @return array<string, array{skills:array<int, array{skill:string, count:int, average:float}>, total_scores:int, overall_average:float}>
     */
    #[Computed]
    public function progress(): array
    {
        return ProgressSummary::build((int) Auth::id(), $this->window['from'], $this->window['to'])['by_program'];
    }

    /**
     * Score trend across the window (monthly or yearly buckets).
     *
     * @return array<int, array{label:string, avg:float}>
     */
    #[Computed]
    public function trend(): array
    {
        return CoachMetrics::rangeTrend((int) Auth::id(), $this->window['from'], $this->window['to']);
    }

    /** Overall average score across the window — null if none recorded. */
    #[Computed]
    public function overallAverage(): ?float
    {
        return CoachMetrics::averageInRange((int) Auth::id(), $this->window['from'], $this->window['to']);
    }
}
