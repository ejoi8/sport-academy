<?php

namespace App\Support\Reporting;

use App\Models\Attendance;
use App\Models\TrainingSession;
use Illuminate\Support\Carbon;

/**
 * Attendance & delivery for one month: how many sessions ran and how well they were attended.
 * Pass a $coachId to scope to the sessions that coach led (that's how "a coach sees only their
 * own" is enforced). "Attended" = present + late; the rate is over all marked attendances.
 */
class AttendanceSummary
{
    /**
     * @return array{
     *     period:string, coach_id:?int, sessions_delivered:int,
     *     present:int, late:int, absent:int, excused:int, attended:int, total_marked:int,
     *     attendance_rate:float, no_show_rate:float,
     *     by_program:array<string, array{sessions:int, attendances:int, attended:int, rate:float}>
     * }
     */
    public static function for(string $period, ?int $coachId = null): array
    {
        $month = Carbon::parse($period.'-01');

        return static::forRange($month->copy()->startOfMonth(), $month->copy()->endOfMonth(), $coachId);
    }

    /**
     * The same roll-up over an arbitrary (inclusive) date range, so a report can span a day, month,
     * year or custom window. `for()` is the single-month shorthand.
     *
     * @return array{
     *     period:string, coach_id:?int, sessions_delivered:int,
     *     present:int, late:int, absent:int, excused:int, attended:int, total_marked:int,
     *     attendance_rate:float, no_show_rate:float,
     *     by_program:array<string, array{sessions:int, attendances:int, attended:int, rate:float}>
     * }
     */
    public static function forRange(Carbon $start, Carbon $end, ?int $coachId = null): array
    {
        $startStr = $start->toDateString();
        $endStr = $end->toDateString();

        $sessions = TrainingSession::query()
            ->whereBetween('session_date', [$startStr, $endStr])
            ->when($coachId, fn ($q) => $q->where('coach_id', $coachId))
            ->with('offering.program')
            ->get();

        $attendances = Attendance::query()
            ->whereHas('trainingSession', fn ($q) => $q
                ->whereBetween('session_date', [$startStr, $endStr])
                ->when($coachId, fn ($q) => $q->where('coach_id', $coachId)))
            ->with('trainingSession.offering.program')
            ->get();

        $byProgram = [];

        foreach ($sessions as $session) {
            $program = $session->offering?->program?->name ?? 'Unknown';
            $byProgram[$program] ??= ['sessions' => 0, 'attendances' => 0, 'attended' => 0, 'rate' => 0.0];
            $byProgram[$program]['sessions']++;
        }

        $counts = ['present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0];

        foreach ($attendances as $attendance) {
            $status = $attendance->status->value;
            if (isset($counts[$status])) {
                $counts[$status]++;
            }

            $program = $attendance->trainingSession?->offering?->program?->name ?? 'Unknown';
            $byProgram[$program] ??= ['sessions' => 0, 'attendances' => 0, 'attended' => 0, 'rate' => 0.0];
            $byProgram[$program]['attendances']++;
            if ($status === 'present' || $status === 'late') {
                $byProgram[$program]['attended']++;
            }
        }

        foreach ($byProgram as $program => $row) {
            $byProgram[$program]['rate'] = $row['attendances'] > 0
                ? round($row['attended'] / $row['attendances'] * 100, 1)
                : 0.0;
        }

        $attended = $counts['present'] + $counts['late'];
        $totalMarked = array_sum($counts);

        ksort($byProgram);

        return [
            'period' => $start->format('Y-m'),
            'coach_id' => $coachId,
            'sessions_delivered' => $sessions->count(),
            'present' => $counts['present'],
            'late' => $counts['late'],
            'absent' => $counts['absent'],
            'excused' => $counts['excused'],
            'attended' => $attended,
            'total_marked' => $totalMarked,
            'attendance_rate' => $totalMarked > 0 ? round($attended / $totalMarked * 100, 1) : 0.0,
            'no_show_rate' => $totalMarked > 0 ? round($counts['absent'] / $totalMarked * 100, 1) : 0.0,
            'by_program' => $byProgram,
        ];
    }
}
