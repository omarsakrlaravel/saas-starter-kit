<?php

return [

    'api' => [
        'auth_token_expires' => 60,
        'key_token_expires' => 1,
    ],

    'auth' => [
        'min_password_length' => 8,
    ],

    'primary_color' => '#000000',

    'user_model' => \App\Models\User::class,
    'show_docs' => env('WAVE_DOCS', true),
    'demo' => env('WAVE_DEMO', false),
    'dev_bar' => env('WAVE_BAR', false),
    'default_user_role' => 'registered',

    'billing_provider' => env('BILLING_PROVIDER', 'stripe'),

    'paddle' => [
        'vendor' => env('PADDLE_VENDOR_ID', ''),
        'api_key' => env('PADDLE_API_KEY', ''),
        'env' => env('PADDLE_ENV', 'sandbox'),
        'public_key' => env('PADDLE_PUBLIC_KEY', ''),
        'webhook_secret' => env('PADDLE_WEBHOOK_SECRET', ''),
    ],

    'stripe' => [
        'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
        'secret_key' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'settings' => [
        'site.title' => env('SITE_TITLE', 'Wave'),
        'site.description' => env('SITE_DESCRIPTION', 'The Software as a Service Starter Kit built with Laravel'),
        'site.google_analytics_tracking_id' => env('GOOGLE_ANALYTICS_ID'),
        'site.favicon' => '/wave/favicon.png',
        'site.favicon_dark' => '/wave/favicon-dark.png',
        'digital-ocean.enabled' => false,
    ],

];
