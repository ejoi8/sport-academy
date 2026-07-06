<?php

namespace App\Support;

class PaymentInstructions
{
    /**
     * @return array<string, string>
     */
    public static function hostedGatewayOptions(): array
    {
        $gateways = config('payment-gateway.gateways', []);
        $options = [];

        foreach ($gateways as $name => $gatewayConfig) {
            if (! is_string($name) || $name === 'manual' || ! is_array($gatewayConfig)) {
                continue;
            }

            if (! self::gatewayIsReady($name, $gatewayConfig)) {
                continue;
            }

            $options[$name] = self::gatewayLabel($name);
        }

        return $options;
    }

    public static function defaultHostedGateway(): ?string
    {
        $options = self::hostedGatewayOptions();
        $configured = (string) config('payment-gateway.default', '');

        if (array_key_exists($configured, $options)) {
            return $configured;
        }

        return array_key_first($options);
    }

    public static function summary(?string $gateway = null): string
    {
        $options = self::hostedGatewayOptions();

        if ($options !== []) {
            $gateway ??= self::defaultHostedGateway();

            if ($gateway && array_key_exists($gateway, $options)) {
                return 'Complete payment online with '.$options[$gateway].'. The booking activates automatically after the provider confirms it.';
            }

            return 'Choose your preferred online payment provider at checkout. The booking activates automatically after the provider confirms it.';
        }

        return 'Complete payment by bank transfer or DuitNow QR, then share your booking reference with the academy for confirmation.';
    }

    /**
     * @return array<int, string>
     */
    public static function lines(): array
    {
        if (self::usesHostedGateway()) {
            return [
                'Choose a payment provider, then use the Pay now button to open the secure checkout page.',
                'The academy activates the enrolment automatically after the gateway confirms payment.',
                'Keep your booking reference handy if you need support from the academy team.',
            ];
        }

        return [
            'Pay by bank transfer or DuitNow QR using the academy account shared after booking.',
            'Include your booking reference in the payment note or message.',
            'The academy confirms payment manually and activates the enrolment once received.',
        ];
    }

    public static function usesHostedGateway(): bool
    {
        return self::hostedGatewayOptions() !== [];
    }

    public static function gatewayLabel(string $gateway): string
    {
        return match ($gateway) {
            'billplz' => 'Billplz FPX',
            'toyyibpay' => 'toyyibPay FPX',
            'chip' => 'CHIP',
            'stripe' => 'Stripe Checkout',
            'paypal' => 'PayPal',
            default => ucfirst(str_replace(['_', '-'], ' ', $gateway)),
        };
    }

    /**
     * @param  array<string, mixed>  $gatewayConfig
     */
    protected static function gatewayIsReady(string $gateway, array $gatewayConfig): bool
    {
        if (! filled($gatewayConfig['driver'] ?? null)) {
            return false;
        }

        $requiredFields = match ($gateway) {
            'chip' => ['api_key', 'brand_id', 'public_key'],
            'billplz' => ['api_key', 'collection_id', 'x_signature_key'],
            'toyyibpay' => ['secret_key', 'category_code'],
            'stripe' => ['secret_key', 'webhook_secret'],
            'paypal' => ['client_id', 'client_secret', 'webhook_id'],
            default => [],
        };

        foreach ($requiredFields as $field) {
            if (! filled($gatewayConfig[$field] ?? null)) {
                return false;
            }
        }

        return true;
    }
}
