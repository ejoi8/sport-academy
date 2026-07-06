<?php

namespace App\Http\Controllers\Payments;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\GatewayPayment;
use App\Support\PaymentInstructions;
use Ejoi\PaymentGateway\Data\Customer;
use Ejoi\PaymentGateway\Data\Money;
use Ejoi\PaymentGateway\Data\PaymentRequest;
use Ejoi\PaymentGateway\Laravel\Payments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CheckoutController
{
    public function __invoke(
        Request $request,
        Enrollment $enrollment,
        Payments $payments,
    ): RedirectResponse {
        $gatewayOptions = PaymentInstructions::hostedGatewayOptions();

        abort_if($gatewayOptions === [], 404);

        $validated = $request->validate([
            'gateway' => ['nullable', 'string', Rule::in(array_keys($gatewayOptions))],
        ]);

        $gateway = (string) ($validated['gateway'] ?? PaymentInstructions::defaultHostedGateway() ?? '');

        abort_unless(array_key_exists($gateway, $gatewayOptions), 404);

        $enrollment->loadMissing('student.parent', 'offering.program');

        abort_unless($enrollment->student?->parent_id === $request->user()?->id, 403);
        abort_unless($enrollment->isOnlineBooking(), 404);
        abort_unless($enrollment->status === EnrollmentStatus::Pending, 409);
        abort_unless(filled($enrollment->booking_reference), 409);

        // Reuse ladder — avoids a duplicate live bill when a parent clicks "Pay now"
        // more than once (double tab, back button, stale bookmarked checkout URL):
        //   1. Load the most recent payment attempt for this booking, if any.
        //   2. If it's still pending and we have a gateway reference, reconcile it
        //      first — it may already be paid at the gateway even though our copy
        //      hasn't heard yet (webhook missed/delayed).
        //      -> Now paid: send the parent to the return page instead of billing
        //         them again; activation happens there/via the listener.
        //   3. Still pending, SAME gateway as requested, and a checkout_url is on
        //      file -> send the parent back to that existing hosted page rather
        //      than minting a new one.
        //   4. Otherwise (no prior payment, or it's failed/expired/cancelled, or
        //      the parent picked a different gateway this time) -> create fresh.
        $existingPayment = GatewayPayment::query()
            ->where('reference', $enrollment->booking_reference)
            ->latest('id')
            ->first();

        if ($existingPayment && $existingPayment->status->isPending() && filled($existingPayment->gateway_reference)) {
            $existingPayment = $payments->reconcile($existingPayment);
        }

        if ($existingPayment?->status->isPaid()) {
            return redirect()->route('payments.return', $enrollment);
        }

        if (
            $existingPayment
            && $existingPayment->status->isPending()
            && $existingPayment->gateway === $gateway
            && filled($existingPayment->checkout_url)
        ) {
            return redirect()->away($existingPayment->checkout_url);
        }

        $payment = $payments->create($gateway, new PaymentRequest(
            reference: $enrollment->booking_reference,
            amount: Money::fromMinor($enrollment->price_sen, 'MYR'),
            description: sprintf(
                '%s · %s',
                $enrollment->offering?->program?->name ?? 'Football Academy booking',
                $enrollment->booking_reference,
            ),
            customer: new Customer(
                $enrollment->student?->parent?->email ?? $request->user()->email,
                $enrollment->student?->parent?->name ?? $request->user()->name,
                $enrollment->student?->parent?->phone,
            ),
            redirectUrl: route('payments.return', $enrollment),
            callbackUrl: route('payment-gateway.webhook', $gateway),
            metadata: [
                'enrollment_id' => $enrollment->id,
                'student_id' => $enrollment->student_id,
            ],
        ));

        abort_unless(filled($payment->checkout_url), 409);

        return redirect()->away($payment->checkout_url);
    }
}
