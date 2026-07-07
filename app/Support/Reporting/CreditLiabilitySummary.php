<?php

namespace App\Support\Reporting;

use App\Models\Enrollment;

/**
 * Outstanding credit liability — a snapshot (not month-scoped) of prepaid-but-undelivered
 * sessions the academy still owes. Each remaining credit is valued at that enrolment's
 * price-per-session (price ÷ sessions, integer sen), so the total is the RM value of training
 * already paid for and not yet delivered. Expired credits are excluded (they can't be spent).
 */
class CreditLiabilitySummary
{
    /**
     * @return array{
     *     total_remaining_credits:int, total_value_sen:int, over_delivered_count:int,
     *     by_program:array<string, array{remaining_credits:int, value_sen:int, enrollments:int}>
     * }
     */
    public static function build(): array
    {
        $enrollments = Enrollment::query()
            ->whereIn('status', ['active', 'pending', 'overdue'])
            ->where(fn ($q) => $q->whereNull('credits_expire_at')->orWhereDate('credits_expire_at', '>=', today()))
            ->withCount(['attendances as used_credits' => fn ($q) => $q->whereIn('status', Enrollment::CREDIT_CONSUMING_STATUSES)])
            ->with('offering.program')
            ->get();

        $byProgram = [];
        $totalCredits = 0;
        $totalValue = 0;
        $overDelivered = 0;

        foreach ($enrollments as $enrollment) {
            if ($enrollment->used_credits > $enrollment->sessions_included) {
                $overDelivered++;
            }

            $remaining = max(0, $enrollment->sessions_included - $enrollment->used_credits);

            if ($remaining === 0) {
                continue;
            }

            $perSession = $enrollment->sessions_included > 0
                ? intdiv((int) $enrollment->price_sen, $enrollment->sessions_included)
                : 0;
            $value = $remaining * $perSession;

            $program = $enrollment->offering?->program?->name ?? 'Unknown';
            $byProgram[$program] ??= ['remaining_credits' => 0, 'value_sen' => 0, 'enrollments' => 0];
            $byProgram[$program]['remaining_credits'] += $remaining;
            $byProgram[$program]['value_sen'] += $value;
            $byProgram[$program]['enrollments']++;

            $totalCredits += $remaining;
            $totalValue += $value;
        }

        ksort($byProgram);

        return [
            'total_remaining_credits' => $totalCredits,
            'total_value_sen' => $totalValue,
            'over_delivered_count' => $overDelivered,
            'by_program' => $byProgram,
        ];
    }
}
