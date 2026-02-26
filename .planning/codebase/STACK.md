# Technology Stack

**Analysis Date:** 2026-02-26

## Languages

**Primary:**
- PHP 8.4 - All application code (`composer.json` requires `^8.2`)

**Secondary:**
- JavaScript - Frontend interactivity, build tooling (`package.json`)
- Blade - Templating (`resources/themes/anchor/`)

## Runtime

**Environment:**
- PHP 8.2+ (supports 8.2, 8.3, 8.4)
- Node.js - Required for Vite build tooling
- Database: SQLite (default), MySQL/PostgreSQL compatible (`config/database.php`)

**Package Manager:**
- Composer - PHP dependencies
- npm - Frontend dependencies
- Lockfiles: `composer.lock`, `package-lock.json` present

## Frameworks

**Core:**
- Laravel 12 - `laravel/framework ^12.51` - Web framework (`composer.json`)
- Laravel Folio v1 - `laravel/folio ^1.1` - File-based routing
- Filament v4 - `filament/filament ^4.7` - Admin panel
- Livewire v3 - `livewire/livewire ^3.6.4` - Reactive components
- Volt v1 - `livewire/volt` - Single-file Livewire components

**Frontend:**
- Tailwind CSS v4 - `tailwindcss ^4.1.12` - Utility CSS (`tailwind.config.js`)
- Alpine.js v3 - `alpinejs ^3.4.2` - Lightweight JS framework

**Testing:**
- Pest v4 - `pestphp/pest ^4.3` - PHP testing framework
- PHPUnit v12 - `phpunit/phpunit ^12.5.4` - Underlying test runner
- Pest Livewire Plugin - `pestphp/pest-plugin-livewire ^4.1`
- Laravel Dusk v8 - `laravel/dusk ^8.2` - Browser testing
- Mockery v1.6 - `mockery/mockery ^1.6`

**Build/Dev:**
- Vite v6.4 - `vite ^6.4` - Build tool (`vite.config.js`)
- Laravel Pint v1 - `laravel/pint ^1.26` - Code formatter (`pint.json`)
- Concurrently - Dev server orchestration (`composer run dev`)

## Key Dependencies

**Critical:**
- `laravel/cashier ^16` - Stripe payment processing (`app/Listeners/HandleStripeWebhook.php`)
- `stripe/stripe-php ^17.3` - Stripe PHP SDK
- `devdojo/auth ^2.2.0` - Authentication with social login (`config/devdojo/auth/providers.php`)
- `tymon/jwt-auth ^2.2` - JWT API authentication (`config/jwt.php`)
- `spatie/laravel-permission ^6.12` - Role-based access control (`config/permission.php`)
- `bezhansalleh/filament-shield ^4.0` - Filament permission management

**Infrastructure:**
- `intervention/image ^3.11` - Image manipulation
- `lab404/laravel-impersonate ^1.7.5` - User impersonation (`bootstrap/app.php`)
- `gehrisandro/tailwind-merge-laravel ^1.3` - Tailwind class merging
- `codeat3/blade-phosphor-icons ^2.0` - Icon library
- `ralphjsmit/livewire-urls ^1.5` - Livewire URL handling

**Monitoring:**
- `laravel/nightwatch ^1.22` - Performance monitoring (dev)
- `laravel/pail ^1.2.2` - Log tailing
- `spatie/laravel-ignition ^2.9` - Error page enhancement

## Configuration

**Environment:**
- `.env` files with `.env.example` template
- Key vars: `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `DB_CONNECTION`, `BILLING_PROVIDER`
- Config-based settings via `config/wave.php` (no database settings table)

**Build:**
- `vite.config.js` - Vite with theme support
- `tailwind.config.js` - Tailwind CSS configuration
- `pint.json` - Laravel preset code formatting
- `phpunit.xml` - Test configuration

## Platform Requirements

**Development:**
- Any platform with PHP 8.2+ and Node.js
- `composer run dev` starts server, queue, logs, and Vite concurrently

**Production:**
- PHP 8.2+ with required extensions
- Queue worker for background jobs (database or Redis driver)
- Cron for scheduled commands (activity cleanup, account deletions, plan changes)

---

*Stack analysis: 2026-02-26*
*Update after major dependency changes*
