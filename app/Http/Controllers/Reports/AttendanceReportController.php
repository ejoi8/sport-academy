<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Support\Reporting\AttendanceSummary;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Print-friendly (and CSV) attendance & delivery report for one month. Admins see everyone (with
 * an optional `?coach=` filter); a plain coach only ever sees their own sessions.
 */
class AttendanceReportController extends Controller
{
    public function __invoke(Request $request): View|StreamedResponse
    {
        $user = $request->user();
        abort_unless((bool) $user?->hasAnyRole(['admin', 'super_admin', 'coach']), 403);

        $period = $request->string('period')->toString() ?: now()->format('Y-m');
        $isAdmin = $user->hasAnyRole(['admin', 'super_admin']);
        $coachId = $isAdmin ? ($request->integer('coach') ?: null) : (int) $user->id;

        $data = AttendanceSummary::for($period, $coachId);

        if ($request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($data): void {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Program', 'Sessions', 'Attendances', 'Attended', 'Rate %']);
                foreach ($data['by_program'] as $program => $row) {
                    fputcsv($out, [$program, $row['sessions'], $row['attendances'], $row['attended'], $row['rate']]);
                }
                fputcsv($out, []);
                fputcsv($out, ['Total', $data['sessions_delivered'], $data['total_marked'], $data['attended'], $data['attendance_rate']]);
                fclose($out);
            }, 'attendance-'.$data['period'].'.csv', ['Content-Type' => 'text/csv']);
        }

        return view('reports.print.attendance', ['data' => $data]);
    }
}
