<?php

namespace App\Support\Reporting;

use App\Models\AssessmentScore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Small coach-scoped assessment metrics shared by the console Home summary and the full report —
 * the score trend and the all-time average. Attendance/delivery live in {@see AttendanceSummary}
 * and per-programme skill breakdowns in {@see ProgressSummary}.
 */
class CoachMetrics
{
    /**
     * Average assessment score for each of the last N months (0 = no scores that month).
     *
     * @return array<int, array{label:string, avg:float}>
     */
    public static function trend(int $coachId, int $months = 6): array
    {
        $out = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->startOfMonth()->subMonthsNoOverflow($i);

            $avg = AssessmentScore::whereHas('attendance', fn (Builder $query) => $query
                ->where('coach_id', $coachId)
                ->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()]))
                ->avg('score');

            $out[] = ['label' => $month->format('M'), 'avg' => $avg ? round((float) $avg, 1) : 0.0];
        }

        return $out;
    }

    /** All-time overall average score across the coach's assessments — null if none recorded yet. */
    public static function overallAverage(int $coachId): ?float
    {
        $avg = AssessmentScore::whereHas('attendance', fn (Builder $query) => $query->where('coach_id', $coachId))->avg('score');

        return $avg ? round((float) $avg, 1) : null;
    }

    /**
     * Score trend across an arbitrary window — bucketed by month (<= 14 months) or by year (longer),
     * so the chart always has a sensible number of bars whatever duration the coach picks.
     *
     * @return array<int, array{label:string, avg:float}>
     */
    public static function rangeTrend(int $coachId, Carbon $from, Carbon $to): array
    {
        $out = [];

        if ($from->diffInMonths($to) <= 13) {
            $cursor = $from->copy()->startOfMonth();

            while ($cursor->lte($to)) {
                $out[] = [
                    'label' => $cursor->format('M'),
                    'avg' => self::avgBetween($coachId, $cursor->copy()->startOfMonth(), $cursor->copy()->endOfMonth()),
                ];
                $cursor->addMonthNoOverflow();
            }

            return $out;
        }

        for ($year = $from->year; $year <= $to->year; $year++) {
            $start = Carbon::create($year)->startOfYear()->max($from);
            $end = Carbon::create($year)->endOfYear()->min($to);

            $out[] = ['label' => (string) $year, 'avg' => self::avgBetween($coachId, $start, $end)];
        }

        return $out;
    }

    /** Average score for a coach within an inclusive date window — null if none recorded. */
    public static function averageInRange(int $coachId, Carbon $from, Carbon $to): ?float
    {
        return self::avgBetween($coachId, $from, $to) ?: null;
    }

    private static function avgBetween(int $coachId, Carbon $from, Carbon $to): float
    {
        $avg = AssessmentScore::whereHas('attendance', fn (Builder $query) => $query
            ->where('coach_id', $coachId)
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]))
            ->avg('score');

        return $avg ? round((float) $avg, 1) : 0.0;
    }
}
