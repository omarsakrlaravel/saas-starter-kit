# External Integrations

**Analysis Date:** 2026-02-26

## APIs & External Services

**Payment Processing:**
- Stripe - Subscription billing, one-time charges, invoices, coupons
  - SDK/Client: `laravel/cashier ^16`, `stripe/stripe-php ^17.3`
  - Auth: API keys in `STRIPE_KEY`, `STRIPE_SECRET` env vars
  - Endpoints used: Checkout sessions, customer portal, subscriptions, invoices, coupons, promotion codes
  - Sync commands: `app/Console/Commands/StripeSyncInvoices.php`, `StripeSyncCoupons.php`, `StripeSyncAll.php`
  - Filament integration: `app/Filament/Resources/Invoices/InvoiceResource.php`, `app/Filament/Resources/Organizations/OrganizationResource.php`

**Email/SMS:**
- Mailtrap - Default SMTP in `.env.example`
- Mailgun - Configured in `config/services.php`
- SparkPost - Configured in `config/services.php`
- AWS SES - Environment variables in `.env.example` (unconfigured)
- Configuration: `config/mail.php`

## Data Storage

**Databases:**
- SQLite - Default development database (`database/database.sqlite`)
- MySQL - Test database configured as `saas-starter-kit-pest` in `phpunit.xml`
- PostgreSQL - Supported via `config/database.php`
- Connection: `DB_CONNECTION`, `DB_DATABASE` env vars

**File Storage:**
- Local filesystem - Default public storage (`config/filesystems.php`)
- AWS S3 - Available but unconfigured (`AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`)

**Caching:**
- File-based caching (default)
- Redis optional: `REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT` env vars
- Session storage: Database driver

## Authentication & Identity

**Auth Provider:**
- devdojo/auth - Custom auth system with social login (`config/devdojo/auth/providers.php`)
  - Implementation: Email/password + OAuth providers
  - Session management: Laravel session with database driver

**OAuth Integrations (via devdojo/auth):**
- Google - Active: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`
- GitHub - Active: `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET`
- Facebook - Inactive: `FACEBOOK_CLIENT_ID`, `FACEBOOK_CLIENT_SECRET`
- Twitter/X - Inactive: `TWITTER_CLIENT_ID`, `TWITTER_CLIENT_SECRET`
- LinkedIn - Inactive: `LINKEDIN_CLIENT_ID`, `LINKEDIN_CLIENT_SECRET`
- GitLab, Bitbucket, Slack, Apple, Microsoft, Pinterest, Reddit, TikTok, Twitch - Inactive

**API Authentication:**
- JWT tokens via `tymon/jwt-auth` for API guard (`config/auth.php`)
- Custom API key tokens via `wave/src/ApiKey.php`

## Monitoring & Observability

**Performance Monitoring:**
- Laravel Nightwatch (`laravel/nightwatch ^1.22`) - Development server included in `composer run dev`
  - Token: `NIGHTWATCH_TOKEN` env var

**Error Tracking:**
- Laravel Ignition (`spatie/laravel-ignition`) - Enhanced error pages in development

**Analytics:**
- Google Analytics - `GOOGLE_ANALYTICS_ID` or `ANALYTICS_PROPERTY_ID` env var
  - Config: `config/filament-google-analytics.php`

**Logs:**
- Laravel Pail for log tailing in development
- Standard Laravel logging (file-based by default)

## CI/CD & Deployment

**Hosting:**
- Not configured (framework-agnostic)
- Compatible with any PHP hosting (Forge, Vapor, VPS, etc.)

**CI Pipeline:**
- Not configured in repository

## Environment Configuration

**Development:**
- Required env vars: `DB_CONNECTION`, `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`
- Secrets location: `.env` (gitignored), `.env.example` for template
- Demo mode: `WAVE_DEMO` env var enables theme switching and demo features

**Production:**
- Queue worker required for: activity logging, account deletions, plan changes
- Cron required for: `accounts:process-deletions` (daily), `activity:clean` (daily), `wave:apply-pending-plan-changes` (hourly)

## Webhooks & Callbacks

**Incoming:**
- Stripe - `stripe/*` endpoint (excluded from CSRF in `bootstrap/app.php`)
  - Handler: `app/Listeners/HandleStripeWebhook.php`
  - Verification: Stripe signature validation via Cashier
  - Events handled:
    - `checkout.session.completed` - New subscription creation
    - `customer.subscription.updated/deleted` - Subscription lifecycle
    - `invoice.created/paid/payment_failed/voided` - Invoice events
    - `charge.succeeded/failed/refunded/updated` - Charge events
    - `coupon.created/updated/deleted` - Coupon sync
    - `promotion_code.created/updated` - Promo code sync

**Outgoing:**
- None configured

## Activity Logging

- Custom system: `wave/src/ActivityLog.php`, `wave/src/Jobs/CreateActivityLog.php`
- Configuration: `config/activity.php`
- Enable/disable: `ACTIVITY_LOG_ENABLED` env var
- Queued logging: `ACTIVITY_LOG_QUEUE` env var
- Retention: `ACTIVITY_LOG_RETENTION_DAYS` (default 90 days)
- Cleanup: `php artisan activity:clean`
- Events logged: `Login`, `Logout` via `app/Listeners/LogSuccessfulLogin.php`, `LogSuccessfulLogout.php`

## Broadcasting (Optional)

- Pusher - Configured but disabled by default
  - Env vars: `PUSHER_APP_ID`, `PUSHER_APP_KEY`, `PUSHER_APP_SECRET`, `PUSHER_APP_CLUSTER`

---

*Integration audit: 2026-02-26*
*Update when adding/removing external services*
