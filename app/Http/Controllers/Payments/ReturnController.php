<?php

namespace App\Http\Controllers\Payments;

use App\Models\Enrollment;
use App\Models\GatewayPayment;
use Ejoi\PaymentGateway\Laravel\Payments;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReturnController
{
    public function __invoke(
        Request $request,
        Enrollment $enrollment,
        Payments $payments,
    ): View {
        $enrollment->loadMissing('student.parent', 'offering.program');

        abort_unless($enrollment->student?->parent_id === $request->user()?->id, 403);

        $payment = GatewayPayment::query()
            ->where('reference', $enrollment->booking_reference)
            ->latest('id')
            ->first();

        if ($payment && $payment->status->isPending() && filled($payment->gateway_reference)) {
            $payment = $payments->reconcile($payment);
        }

        return view('payments.return', [
            'enrollment' => $enrollment,
            'payment' => $payment,
        ]);
    }
}
