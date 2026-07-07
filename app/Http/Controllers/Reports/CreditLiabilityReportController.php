<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Support\Reporting\CreditLiabilitySummary;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Print-friendly (and CSV) credit-liability snapshot: prepaid-but-undelivered sessions the
 * academy still owes. Staff only. `?format=csv` downloads; otherwise it renders the print sheet.
 */
class CreditLiabilityReportController extends Controller
{
    public function __invoke(Request $request): View|StreamedResponse
    {
        abort_unless((bool) $request->user()?->hasAnyRole(['admin', 'super_admin']), 403);

        $data = CreditLiabilitySummary::build();

        if ($request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($data): void {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Program', 'Remaining credits', 'Value (RM)', 'Enrolments']);
                foreach ($data['by_program'] as $program => $row) {
                    fputcsv($out, [
                        $program,
                        $row['remaining_credits'],
                        number_format($row['value_sen'] / 100, 2, '.', ''),
                        $row['enrollments'],
                    ]);
                }
                fputcsv($out, []);
                fputcsv($out, ['Total', $data['total_remaining_credits'], number_format($data['total_value_sen'] / 100, 2, '.', '')]);
                fclose($out);
            }, 'credit-liability-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
        }

        return view('reports.print.credit-liability', ['data' => $data]);
    }
}
