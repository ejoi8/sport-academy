<?php

namespace App\Support\Reporting;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;

/**
 * Revenue & outstanding for one billing month, derived from enrolments (money is integer sen).
 *
 *   billed      = every real (non-cancelled) enrolment invoiced for the month
 *   collected   = the active (paid) ones
 *   outstanding = the pending + overdue ones — billed but not yet collected
 *
 * so billed = collected + outstanding, always.
 */
class RevenueSummary
{
    /**
     * @return array{
     *     period:string, billed_sen:int, collected_sen:int, outstanding_sen:int,
     *     enrollment_count:int, new_count:int, renewing_count:int,
     *     by_program:array<string, array{billed_sen:int, collected_sen:int, outstanding_sen:int, active:int, pending:int, overdue:int}>
     * }
     */
    public static function for(string $period): array
    {
        $enrollments = Enrollment::query()
            ->whereIn('status', [EnrollmentStatus::Active->value, EnrollmentStatus::Pending->value, EnrollmentStatus::Overdue->value])
            ->whereHas('offering', fn ($q) => $q->where('period', $period))
            ->with('offering.program')
            ->get();

        $byProgram = [];
        $billed = 0;
        $collected = 0;
        $outstanding = 0;

        foreach ($enrollments as $enrollment) {
            $program = $enrollment->offering?->program?->name ?? 'Unknown';
            $byProgram[$program] ??= ['billed_sen' => 0, 'collected_sen' => 0, 'outstanding_sen' => 0, 'active' => 0, 'pending' => 0, 'overdue' => 0];

            $price = (int) $enrollment->price_sen;
            $billed += $price;
            $byProgram[$program]['billed_sen'] += $price;

            if ($enrollment->status === EnrollmentStatus::Active) {
                $collected += $price;
                $byProgram[$program]['collected_sen'] += $price;
                $byProgram[$program]['active']++;
            } else {
                $outstanding += $price;
                $byProgram[$program]['outstanding_sen'] += $price;
                $byProgram[$program][$enrollment->status->value]++;
            }
        }

        // Renewing = the student already had an enrolment in an earlier month; new = first time.
        $studentIds = $enrollments->pluck('student_id')->unique();
        $renewingStudentIds = Enrollment::query()
            ->whereIn('student_id', $studentIds)
            ->whereHas('offering', fn ($q) => $q->where('period', '<', $period))
            ->pluck('student_id')
            ->flip();

        $renewing = $enrollments->filter(fn (Enrollment $e): bool => $renewingStudentIds->has($e->student_id))->count();

        ksort($byProgram);

        return [
            'period' => $period,
            'billed_sen' => $billed,
            'collected_sen' => $collected,
            'outstanding_sen' => $outstanding,
            'enrollment_count' => $enrollments->count(),
            'new_count' => $enrollments->count() - $renewing,
            'renewing_count' => $renewing,
            'by_program' => $byProgram,
        ];
    }
}
