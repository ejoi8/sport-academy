<?php

return [
    'default' => env('PAYMENT_GATEWAY', 'billplz'),

    'gateways' => [
        'chip' => [
            'driver' => 'chip',
            'api_key' => env('CHIP_API_KEY', ''),
            'brand_id' => env('CHIP_BRAND_ID', ''),
            'public_key' => env('CHIP_PUBLIC_KEY', ''),
            'sandbox' => env('CHIP_SANDBOX', true),
        ],

        'billplz' => [
            'driver' => 'billplz',
            'api_key' => env('BILLPLZ_API_KEY', ''),
            'collection_id' => env('BILLPLZ_COLLECTION_ID', ''),
            'x_signature_key' => env('BILLPLZ_X_SIGNATURE_KEY', ''),
            'sandbox' => env('BILLPLZ_SANDBOX', true),
        ],

        'toyyibpay' => [
            'driver' => 'toyyibpay',
            'secret_key' => env('TOYYIBPAY_SECRET_KEY', ''),
            'category_code' => env('TOYYIBPAY_CATEGORY_CODE', ''),
            'sandbox' => env('TOYYIBPAY_SANDBOX', true),
        ],

        'stripe' => [
            'driver' => 'stripe',
            'secret_key' => env('STRIPE_SECRET_KEY', ''),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
            'sandbox' => env('STRIPE_SANDBOX', true),
        ],

        'paypal' => [
            'driver' => 'paypal',
            'client_id' => env('PAYPAL_CLIENT_ID', ''),
            'client_secret' => env('PAYPAL_CLIENT_SECRET', ''),
            'webhook_id' => env('PAYPAL_WEBHOOK_ID', ''),
            'sandbox' => env('PAYPAL_SANDBOX', true),
        ],

        'manual' => [
            'driver' => 'manual',
            'bank_name' => env('MANUAL_BANK_NAME', ''),
            'account_name' => env('MANUAL_ACCOUNT_NAME', ''),
            'account_number' => env('MANUAL_ACCOUNT_NUMBER', ''),
            'instructions' => env('MANUAL_INSTRUCTIONS', 'Transfer the exact amount and upload your receipt.'),
        ],
    ],

    'store' => [
        'enabled' => env('PAYMENT_GATEWAY_STORE', true),
        'tables' => [
            'payments' => 'payments',
            'webhooks' => 'payment_webhooks',
            'proofs' => 'payment_proofs',
        ],
    ],

    'proofs' => [
        'disk' => env('PAYMENT_GATEWAY_PROOF_DISK', 'local'),
        'directory' => 'payment-proofs',
        'max_kb' => 5120,
        'mimes' => ['pdf', 'jpg', 'jpeg', 'png'],
    ],

    'webhook' => [
        'route' => env('PAYMENT_GATEWAY_WEBHOOK_ROUTE', true),
        'prefix' => 'payment-gateway',
        'middleware' => [],
    ],

    'reconcile' => [
        'enabled' => env('PAYMENT_GATEWAY_RECONCILE_ENABLED', true),
        'stale_after_minutes' => 5,
    ],

    'notifications' => [
        'enabled' => env('PAYMENT_GATEWAY_NOTIFICATIONS', true),
        'channels' => ['mail'],
        'on' => ['paid', 'failed'],
        'merchant' => [
            'enabled' => true,
            'address' => env('PAYMENT_GATEWAY_ADMIN_EMAIL'),
        ],
        'customer' => [
            'enabled' => true,
        ],
    ],
];
