<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV of all enrolments for accounting/records (staff only). Exports every enrolment — filter-aware
 * export can come later if the file gets unwieldy.
 */
class EnrollmentsExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        abort_unless((bool) $request->user()?->hasAnyRole(['admin', 'super_admin']), 403);

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Child', 'Program', 'Month', 'Status', 'Price (RM)', 'Sessions', 'Credits used', 'Source', 'Reference', 'Booked']);

            Enrollment::query()
                ->with(['student', 'offering.program'])
                ->withCount(['attendances as used_credits' => fn ($q) => $q->whereIn('status', Enrollment::CREDIT_CONSUMING_STATUSES)])
                ->orderByDesc('id')
                ->chunk(500, function ($enrollments) use ($out): void {
                    foreach ($enrollments as $enrollment) {
                        fputcsv($out, [
                            $enrollment->student?->name,
                            $enrollment->offering?->program?->name,
                            $enrollment->offering?->period,
                            $enrollment->status->value,
                            number_format($enrollment->price_sen / 100, 2, '.', ''),
                            $enrollment->sessions_included,
                            (int) $enrollment->used_credits,
                            $enrollment->source,
                            $enrollment->booking_reference,
                            $enrollment->created_at?->toDateString(),
                        ]);
                    }
                });

            fclose($out);
        }, 'enrolments-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}
