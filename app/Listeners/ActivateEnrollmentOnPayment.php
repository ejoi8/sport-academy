<?php

namespace App\Listeners;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use Ejoi\PaymentGateway\Laravel\Events\PaymentStatusChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ActivateEnrollmentOnPayment
{
    public function handle(PaymentStatusChanged $event): void
    {
        if (! $event->payment->status->isPaid()) {
            return;
        }

        // Lock the enrolment row for the duration of the status re-check + update so two
        // concurrent paid events for the same booking (e.g. a gateway webhook racing a manual
        // approval) cannot both read it as Pending and both activate — which would otherwise
        // double-fire BookingConfirmed. The pending guard below becomes decisive under the lock.
        DB::transaction(function () use ($event): void {
            $this->activate($event);
        });
    }

    protected function activate(PaymentStatusChanged $event): void
    {
        $enrollment = Enrollment::query()
            ->where('booking_reference', $event->payment->reference)
            ->lockForUpdate()
            ->first();

        if (! $enrollment) {
            return;
        }

        // Race #1: a paid event lands for a booking that is no longer pending
        // (e.g. a duplicate webhook delivery for a payment, or the parent paid
        // twice — once via the gateway and once recorded manually). Money moved
        // but we must never activate/act twice — flag it so an admin can review
        // and refund if needed. Never silently swallow money that was taken twice.
        if ($enrollment->status !== EnrollmentStatus::Pending) {
            Log::warning('Payment gateway: paid event for a non-pending enrolment (possible duplicate payment).', [
                'reference' => $event->payment->reference,
                'enrollment_id' => $enrollment->id,
                'enrollment_status' => $enrollment->status->value,
            ]);

            activity('enrolments')
                ->performedOn($enrollment)
                ->withProperties([
                    'reference' => $event->payment->reference,
                    'payment_id' => $event->payment->getKey(),
                    'gateway' => $event->payment->gateway,
                    'amount_minor' => (int) $event->payment->amount_minor,
                    'enrollment_status' => $enrollment->status->value,
                ])
                ->log('duplicate payment received');

            return;
        }

        // Race #2: the amount paid does not match the enrolment's snapshot price.
        // Never activate on a mismatch — flag it loudly (log + activity trail) so
        // an admin can see it against the enrolment and decide how to reconcile.
        if ((int) $event->payment->amount_minor !== (int) $enrollment->price_sen) {
            Log::warning('Payment gateway: paid amount does not match the enrolment price — enrolment NOT activated.', [
                'reference' => $event->payment->reference,
                'enrollment_id' => $enrollment->id,
                'expected_amount_minor' => (int) $enrollment->price_sen,
                'received_amount_minor' => (int) $event->payment->amount_minor,
            ]);

            activity('enrolments')
                ->performedOn($enrollment)
                ->withProperties([
                    'reference' => $event->payment->reference,
                    'payment_id' => $event->payment->getKey(),
                    'gateway' => $event->payment->gateway,
                    'expected_amount_minor' => (int) $enrollment->price_sen,
                    'received_amount_minor' => (int) $event->payment->amount_minor,
                ])
                ->log('payment amount mismatch');

            return;
        }

        $enrollment->update([
            'status' => EnrollmentStatus::Active,
        ]);
    }
}
