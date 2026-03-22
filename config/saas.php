<?php

/*
|--------------------------------------------------------------------------
| SaaS Configuration
|--------------------------------------------------------------------------
|
| This file contains all platform-level settings for the SaaS application.
| It covers billing & currency, API authentication, checkout customization,
| storage, UI preferences, and site metadata. Environment variables are
| used for values that change between environments.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | The platform-wide currency used for all plans and pricing display.
    | Must be a 3-letter ISO currency code (e.g., usd, eur, gbp, jpy)
    | matching the currency of your Stripe Price objects.
    |
    | Invoices and transactions store their own currency from Stripe,
    | but all plans are priced in this single currency.
    |
    */

    'currency' => env('SAAS_CURRENCY', 'usd'),

    'api' => [
        'auth_token_expires' => 60,
        'key_token_expires' => 1,
    ],

    'auth' => [
        'min_password_length' => 8,
    ],

    'primary_color' => '#000000',

    'storage' => [
        'disk' => env('STORAGE_DISK', 'local'),
    ],

    'user_model' => \App\Models\User::class,
    'show_docs' => env('SHOW_DOCS', true),
    'demo' => env('APP_DEMO', false),
    'dev_bar' => env('DEV_BAR', false),
    'organizations_enabled' => env('ORGANIZATIONS_ENABLED', true),

    'stripe' => [
        'key' => env('STRIPE_KEY', env('STRIPE_PUBLISHABLE_KEY')),
        'secret' => env('STRIPE_SECRET', env('STRIPE_SECRET_KEY')),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'checkout' => [
        'faq' => [
            [
                'question' => 'Can I cancel anytime?',
                'answer' => 'Yes, you can cancel at any time from your account settings. Your access continues until the end of your current billing period.',
            ],
            [
                'question' => 'How does billing work?',
                'answer' => 'You\'ll be charged at the start of each billing cycle. Upgrades are prorated immediately, and downgrades take effect at the end of your current period.',
            ],
            [
                'question' => 'Can I change my plan later?',
                'answer' => 'Absolutely. You can upgrade or downgrade your plan at any time from your subscription settings.',
            ],
            [
                'question' => 'Is my payment information secure?',
                'answer' => 'Yes, all payments are processed securely through Stripe. We never store your card details on our servers.',
            ],
        ],
    ],

    'settings' => [
        'site.title' => env('SITE_TITLE', 'App'),
        'site.description' => env('SITE_DESCRIPTION', 'Your SaaS Application'),
        'site.google_analytics_tracking_id' => env('GOOGLE_ANALYTICS_ID'),
        'site.favicon' => '/favicon.png',
        'site.favicon_dark' => '/favicon-dark.png',
        'digital-ocean.enabled' => false,
    ],

];
