# Architecture

**Analysis Date:** 2026-02-26

## Pattern Overview

**Overall:** Modular Monolith with Layered Pattern

**Key Characteristics:**
- Core framework in `wave/` directory, application code in `app/`
- Theme-based presentation layer with Blade/Livewire
- Filament-powered admin panel
- Polymorphic billing (User or Organization as billable entity)
- File-based routing via Laravel Folio for theme pages

## Layers

**HTTP Layer:**
- Purpose: Route requests, apply middleware, delegate to controllers/actions
- Contains: Routes, controllers, middleware, Livewire components
- Location: `wave/src/Http/`, `routes/`, `bootstrap/app.php`
- Depends on: Business logic layer
- Used by: Web browsers, API clients

**Business Logic Layer:**
- Purpose: Domain logic, billing operations, subscription management
- Contains: Actions, services, jobs, event listeners
- Location: `wave/src/Actions/`, `wave/src/Services/`, `wave/src/Jobs/`, `app/Listeners/`
- Depends on: Data layer, external services (Stripe)
- Used by: HTTP layer, console commands

**Data Layer:**
- Purpose: Database models, relationships, query scopes
- Contains: Eloquent models, traits, migrations, factories
- Location: `wave/src/` (core models), `app/Models/` (app models), `wave/src/Traits/`
- Depends on: Database, cache
- Used by: Business logic layer, admin panel

**Presentation Layer:**
- Purpose: Render UI, handle user interactions
- Contains: Blade templates, Livewire/Volt components, Alpine.js behaviors
- Location: `resources/themes/anchor/`, `wave/resources/views/`
- Depends on: HTTP layer (data), components
- Used by: End users

**Admin Layer:**
- Purpose: Back-office management of users, plans, subscriptions
- Contains: Filament resources, widgets, pages
- Location: `app/Filament/`
- Depends on: Data layer
- Used by: Administrators

## Data Flow

**Web Request Lifecycle:**

1. Request enters via `public/index.php` → Laravel bootstrap (`bootstrap/app.php`)
2. Middleware stack processes request (auth, organization context, billing)
3. Router matches: Folio pages (`resources/themes/anchor/pages/`), web routes (`wave/routes/web.php`), or API routes (`wave/routes/api.php`)
4. Controller/Action handles business logic
5. Blade template renders with Livewire components for reactivity
6. Response returned to browser

**Billing Flow (Stripe Checkout):**

1. User selects plan → `wave/src/Http/Livewire/Billing/Checkout.php`
2. Stripe Checkout session created with plan metadata
3. User completes payment on Stripe
4. Stripe sends webhook → `cashier.webhook` route
5. `WebhookReceived` event dispatched
6. `app/Listeners/HandleStripeWebhook.php` processes event
7. `app/Listeners/ApplySubscriptionMetadata.php` sets billing context
8. Subscription model updated, user cache cleared

**State Management:**
- Database-backed sessions
- In-memory caching for subscription/billing context per request
- Redis/file cache for plans (30 min), user roles (5-10 min)
- Cache cleared on subscription/role changes via `$user->clearUserCache()`

## Key Abstractions

**Wave Framework (`wave/src/`):**
- Purpose: Core SaaS functionality shared across all Wave apps
- Contains: Models (Plan, Subscription, Invoice), billing logic, auth extensions
- Pattern: Laravel package structure with service provider registration

**Actions (`wave/src/Actions/`):**
- Purpose: Single-responsibility operations
- Examples: `Billing/Stripe/UpdateSubscriptionQuantity.php`, `Reset.php`
- Pattern: Command pattern with `handle()` method

**Services (`wave/src/Services/`):**
- Purpose: Complex domain logic
- Examples: `PlanChangeResolver.php` - determines plan change behavior
- Pattern: Injectable service classes

**Traits (`wave/src/Traits/`):**
- Purpose: Reusable model behavior
- Examples: `HasPlanFeatures` (feature limits), `HasProfileKeyValues` (dynamic kv store), `HasDynamicFields`
- Pattern: Trait-based composition on User/Organization models

**Livewire Components (`wave/src/Http/Livewire/`):**
- Purpose: Reactive UI for billing and notifications
- Examples: `Billing/Checkout.php`, `Billing/Update.php`, `Notifications/Notification.php`
- Pattern: Server-rendered reactivity with Alpine.js enhancement

## Entry Points

**Web Routes:**
- Location: `routes/web.php` → calls `Wave::routes()` → loads `wave/routes/web.php`
- Triggers: Browser requests
- Responsibilities: Auth pages, billing, settings, organization management

**Folio Pages:**
- Location: `resources/themes/anchor/pages/`
- Triggers: URL matching (e.g., `/dashboard`, `/settings/profile`)
- Responsibilities: User-facing pages (dashboard, settings, changelog)

**API Routes:**
- Location: `routes/api.php` → calls `Wave::api()` → loads `wave/routes/api.php`
- Triggers: API requests with JWT token
- Responsibilities: Auth endpoints (login, register, refresh)

**Console Commands:**
- Location: `wave/src/Console/Commands/`, `app/Console/Commands/`
- Triggers: Artisan CLI, scheduled cron
- Key commands: `accounts:process-deletions` (daily), `activity:clean` (daily), `wave:apply-pending-plan-changes` (hourly)

**Filament Admin:**
- Location: `app/Filament/`
- Triggers: Admin panel at `/admin`
- Responsibilities: CRUD for users, plans, subscriptions, organizations, invoices, coupons

## Error Handling

**Strategy:** Middleware-based authorization + exception handling in `bootstrap/app.php`

**Patterns:**
- Form Request validation for user input
- Middleware authorization (`CanManageBilling`, `Subscribed`, `AdminMiddleware`)
- Try-catch in webhook handlers with logging
- Cache-safe fallbacks (return direct query if cache unavailable)
- Database connection check in `WaveServiceProvider::hasDBConnection()`

## Cross-Cutting Concerns

**Activity Logging:**
- `wave/src/ActivityLog.php` - Simple logging API
- Queued via `wave/src/Jobs/CreateActivityLog.php`
- Auto-cleanup via scheduled `activity:clean` command

**Caching:**
- User subscription/admin status: 5-10 minutes
- Active plans: 30 minutes
- Helper files: Permanent until cleared
- Fallbacks for all cache operations when cache unavailable

**Notifications:**
- Email: `wave/src/Notifications/VerifyEmail.php`, `app/Mail/OrganizationInvite.php`
- Database: In-app notifications via `wave/src/Http/Livewire/Notifications/Notification.php`

**Events:**
- Login/Logout → Activity logging
- `WebhookReceived`/`WebhookHandled` → Stripe integration
- Model events (creating, updated, deleted) for cache invalidation

---

*Architecture analysis: 2026-02-26*
*Update when major patterns change*
