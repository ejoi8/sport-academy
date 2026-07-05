<?php

namespace App\Support;

class PaymentInstructions
{
    public static function summary(): string
    {
        return 'Complete payment by bank transfer or DuitNow QR, then share your booking reference with the academy for confirmation.';
    }

    /**
     * @return array<int, string>
     */
    public static function lines(): array
    {
        return [
            'Pay by bank transfer or DuitNow QR using the academy account shared after booking.',
            'Include your booking reference in the payment note or message.',
            'The academy confirms payment manually and activates the enrolment once received.',
        ];
    }
}
