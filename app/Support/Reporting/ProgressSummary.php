<?php

namespace App\Support\Reporting;

use App\Models\AssessmentScore;

/**
 * Cohort progress roll-up: the average assessment score per skill, per program, across every
 * recorded session (the per-student view is the printable student report). Averages are computed
 * from summed scores so they don't drift, and skills come out in rubric (sort_order) order.
 */
class ProgressSummary
{
    /**
     * @return array{
     *     by_program:array<string, array{
     *         skills:array<int, array{skill:string, count:int, average:float}>,
     *         total_scores:int, overall_average:float
     *     }>
     * }
     */
    public static function build(?int $coachId = null, ?\Illuminate\Support\Carbon $from = null, ?\Illuminate\Support\Carbon $to = null): array
    {
        $rows = AssessmentScore::query()
            ->join('attendances', 'assessment_scores.attendance_id', '=', 'attendances.id')
            ->join('training_sessions', 'attendances.training_session_id', '=', 'training_sessions.id')
            ->join('offerings', 'training_sessions.offering_id', '=', 'offerings.id')
            ->join('programs', 'offerings.program_id', '=', 'programs.id')
            ->join('skills', 'assessment_scores.skill_id', '=', 'skills.id')
            // Pass a coach id to scope the roll-up to the sessions that coach assessed — that's how
            // the coach console shows "my players' progress" without a separate query.
            ->when($coachId, fn ($query) => $query->where('attendances.coach_id', $coachId))
            // Optional date window (by session date) so the report can span any duration.
            ->when($from && $to, fn ($query) => $query->whereBetween('training_sessions.session_date', [$from->toDateString(), $to->toDateString()]))
            ->selectRaw('programs.name as program, skills.name as skill, skills.sort_order as sort, count(*) as n, sum(assessment_scores.score) as total')
            ->groupBy('programs.name', 'skills.name', 'skills.sort_order')
            ->orderBy('programs.name')
            ->orderBy('skills.sort_order')
            ->get();

        $byProgram = [];

        foreach ($rows as $row) {
            $byProgram[$row->program] ??= ['skills' => [], 'total_scores' => 0, 'sum' => 0];
            $byProgram[$row->program]['skills'][] = [
                'skill' => $row->skill,
                'count' => (int) $row->n,
                'average' => round($row->total / $row->n, 1),
            ];
            $byProgram[$row->program]['total_scores'] += (int) $row->n;
            $byProgram[$row->program]['sum'] += (int) $row->total;
        }

        foreach ($byProgram as $program => $data) {
            $byProgram[$program]['overall_average'] = $data['total_scores'] > 0
                ? round($data['sum'] / $data['total_scores'], 1)
                : 0.0;
            unset($byProgram[$program]['sum']);
        }

        return ['by_program' => $byProgram];
    }
}
