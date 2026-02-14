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
    'organizations_enabled' => env('WAVE_ORGANIZATIONS_ENABLED', true),

    'stripe' => [
        'key' => env('STRIPE_KEY', env('STRIPE_PUBLISHABLE_KEY')),
        'secret' => env('STRIPE_SECRET', env('STRIPE_SECRET_KEY')),
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
