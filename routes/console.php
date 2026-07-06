<?php

use Ejoi\PaymentGateway\Laravel\Jobs\ReconcilePendingPayments;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Safety net behind the webhook: a missed/delayed callback (common for FPX push
// methods) would otherwise leave a payment — and its enrolment — pending forever
// until the parent happens to revisit the return page. Honour the package's own
// enable switch (config('payment-gateway.reconcile.enabled')) so this can be
// turned off (e.g. in an environment with no outbound HTTP to the gateways).
if (config('payment-gateway.reconcile.enabled', true)) {
    Schedule::job(new ReconcilePendingPayments)
        ->everyFiveMinutes()
        ->withoutOverlapping();
}
