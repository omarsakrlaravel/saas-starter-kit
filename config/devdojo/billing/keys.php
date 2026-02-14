<?php

return [
    'stripe' => [
        'publishable_key' => env('STRIPE_KEY', env('STRIPE_PUBLISHABLE_KEY')),
        'secret_key' => env('STRIPE_SECRET', env('STRIPE_SECRET_KEY')),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],
];
