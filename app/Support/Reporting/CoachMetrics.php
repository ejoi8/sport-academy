<?php

namespace App\Support\Reporting;

use App\Models\AssessmentScore;
use Illuminate\Database\Eloquent\Builder;

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
}
