<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\GatewayPayment;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV of all payments for accounting/records (staff only).
 */
class PaymentsExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        abort_unless((bool) $request->user()?->hasAnyRole(['admin', 'super_admin']), 403);

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Reference', 'Parent', 'Child', 'Program', 'Gateway', 'Status', 'Amount (RM)', 'Paid at', 'Transaction']);

            GatewayPayment::query()
                ->with(['enrollment.student.parent', 'enrollment.offering.program'])
                ->orderByDesc('id')
                ->chunk(500, function ($payments) use ($out): void {
                    foreach ($payments as $payment) {
                        fputcsv($out, [
                            $payment->reference,
                            $payment->enrollment?->student?->parent?->name,
                            $payment->enrollment?->student?->name,
                            $payment->enrollment?->offering?->program?->name,
                            $payment->gateway,
                            $payment->status->value,
                            number_format($payment->amount_minor / 100, 2, '.', ''),
                            $payment->paid_at?->toDateTimeString(),
                            $payment->transaction_id,
                        ]);
                    }
                });

            fclose($out);
        }, 'payments-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}
