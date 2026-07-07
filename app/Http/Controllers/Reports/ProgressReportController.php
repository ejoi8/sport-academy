<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Support\Reporting\ProgressSummary;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Print-friendly (and CSV) cohort progress roll-up — average skill scores per program.
 * Staff (admin / coach) only.
 */
class ProgressReportController extends Controller
{
    public function __invoke(Request $request): View|StreamedResponse
    {
        abort_unless((bool) $request->user()?->hasAnyRole(['admin', 'super_admin', 'coach']), 403);

        $data = ProgressSummary::build();

        if ($request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($data): void {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Program', 'Skill', 'Times scored', 'Average']);
                foreach ($data['by_program'] as $program => $row) {
                    foreach ($row['skills'] as $skill) {
                        fputcsv($out, [$program, $skill['skill'], $skill['count'], $skill['average']]);
                    }
                    fputcsv($out, [$program, 'Overall', $row['total_scores'], $row['overall_average']]);
                }
                fclose($out);
            }, 'program-progress-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
        }

        return view('reports.print.progress', ['data' => $data]);
    }
}
