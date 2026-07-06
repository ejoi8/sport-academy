<?php

namespace App\Models;

use Ejoi\PaymentGateway\Laravel\Models\Payment as BasePayment;
use Ejoi\PaymentGateway\Laravel\Models\PaymentProof;
use Ejoi\PaymentGateway\Laravel\Models\PaymentWebhook;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GatewayPayment extends BasePayment
{
    public function enrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class, 'booking_reference', 'reference');
    }

    /**
     * Pin the foreign key explicitly — the base Payment::proofs() relies on
     * Eloquent's default guess (Str::snake(class_basename($this)).'_id'),
     * which resolves against *this* subclass's name ("gateway_payment_id")
     * instead of the "payment_id" column the migration actually defines.
     * Package limitation when Payment is subclassed; worked around here
     * rather than touching the package.
     */
    public function proofs(): HasMany
    {
        return $this->hasMany(PaymentProof::class, 'payment_id');
    }

    /**
     * Same default-foreign-key gotcha as {@see proofs()}.
     */
    public function webhooks(): HasMany
    {
        return $this->hasMany(PaymentWebhook::class, 'payment_id');
    }
}
