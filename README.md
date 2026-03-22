# SaaS Starter Kit

A Laravel-based SaaS starter kit with billing, authentication, organizations, and admin panel built-in.

## Features

- Stripe billing with subscriptions, invoices, and coupons
- Multi-tenant organizations with seat management
- Filament admin panel
- User authentication with 2FA
- Activity logging
- File management
- Feature flags

## Requirements

- PHP 8.4+
- Node.js 18+
- Composer
- MySQL / SQLite

## Installation

1. Clone the repository
2. Run `composer install && npm install`
3. Copy `.env.example` to `.env` and configure your database and Stripe keys
4. Run `php artisan key:generate`
5. Run `php artisan migrate --seed`
6. Run `composer run dev` to start the development server

## Development

- `composer run dev` -- Start full dev environment (server, queue, logs, Vite)
- `php artisan test` -- Run tests
- `vendor/bin/pint` -- Format code

## License

[MIT License](LICENSE)
