<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Support\Reporting\RevenueSummary;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Print-friendly (and CSV) revenue & outstanding report for one month. Staff only.
 * `?format=csv` downloads; otherwise it renders the print sheet.
 */
class RevenueReportController extends Controller
{
    public function __invoke(Request $request): View|StreamedResponse
    {
        abort_unless((bool) $request->user()?->hasAnyRole(['admin', 'super_admin']), 403);

        $period = $request->string('period')->toString() ?: now()->format('Y-m');
        $data = RevenueSummary::for($period);

        if ($request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($data): void {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Program', 'Billed', 'Collected', 'Outstanding', 'Active', 'Pending', 'Overdue']);
                foreach ($data['by_program'] as $program => $row) {
                    fputcsv($out, [
                        $program,
                        number_format($row['billed_sen'] / 100, 2, '.', ''),
                        number_format($row['collected_sen'] / 100, 2, '.', ''),
                        number_format($row['outstanding_sen'] / 100, 2, '.', ''),
                        $row['active'], $row['pending'], $row['overdue'],
                    ]);
                }
                fputcsv($out, []);
                fputcsv($out, [
                    'Total',
                    number_format($data['billed_sen'] / 100, 2, '.', ''),
                    number_format($data['collected_sen'] / 100, 2, '.', ''),
                    number_format($data['outstanding_sen'] / 100, 2, '.', ''),
                ]);
                fclose($out);
            }, 'revenue-'.$data['period'].'.csv', ['Content-Type' => 'text/csv']);
        }

        return view('reports.print.revenue', ['data' => $data]);
    }
}
