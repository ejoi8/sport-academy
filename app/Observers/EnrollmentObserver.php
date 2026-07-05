<?php

namespace App\Observers;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Notifications\BookingConfirmed;

class EnrollmentObserver
{
    public function updated(Enrollment $enrollment): void
    {
        $previousStatus = data_get($enrollment->getPrevious(), 'status');

        if (! $enrollment->isOnlineBooking()) {
            return;
        }

        if (! $enrollment->wasChanged('status')) {
            return;
        }

        if ($previousStatus !== EnrollmentStatus::Pending->value) {
            return;
        }

        if ($enrollment->status !== EnrollmentStatus::Active) {
            return;
        }

        $enrollment->loadMissing('student.parent', 'offering.program');

        $enrollment->student?->parent?->notify(new BookingConfirmed($enrollment));
    }
}
