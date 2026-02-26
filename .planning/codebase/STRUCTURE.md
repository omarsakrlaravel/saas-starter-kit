# Codebase Structure

**Analysis Date:** 2026-02-26

## Directory Layout

```
saas-starter-kit/
├── app/                        # Application-specific code
│   ├── Actions/                # App-specific actions
│   ├── Console/Commands/       # Artisan commands (Stripe sync, roles)
│   ├── Filament/               # Admin panel
│   │   ├── Resources/          # CRUD resources (Users, Plans, etc.)
│   │   ├── Clusters/           # Resource groupings
│   │   ├── Widgets/            # Dashboard widgets
│   │   └── Pages/              # Custom admin pages
│   ├── Listeners/              # Event listeners (Stripe webhook, activity)
│   ├── Mail/                   # Mailable classes
│   ├── Models/                 # App models (User, Organization, Forms)
│   ├── Policies/               # Authorization policies
│   └── Providers/              # Service providers
│       └── Filament/           # AdminPanelProvider
├── wave/                       # Wave framework core
│   ├── src/
│   │   ├── Actions/            # Framework actions (billing)
│   │   ├── Console/Commands/   # Framework commands
│   │   ├── Facades/            # Wave facade
│   │   ├── Helpers/            # Utility functions (auto-loaded)
│   │   ├── Http/
│   │   │   ├── Controllers/    # Core controllers
│   │   │   ├── Livewire/       # Billing & notification components
│   │   │   └── Middleware/     # Auth, billing, install middleware
│   │   ├── Jobs/               # Queued jobs
│   │   ├── Notifications/      # Email notifications
│   │   ├── Overrides/          # Custom Vite override for demo
│   │   ├── Services/           # Domain services
│   │   ├── Traits/             # Model traits (features, kv, fields)
│   │   ├── *.php               # Core models (Plan, Subscription, Invoice, etc.)
│   │   ├── ActivityLog.php     # Activity logging API
│   │   ├── Wave.php            # Facade provider
│   │   └── WaveServiceProvider.php  # Main service provider
│   ├── routes/                 # Framework routes (web, api)
│   ├── database/migrations/    # Framework migrations
│   └── resources/views/        # Framework views (admin, livewire, partials)
├── resources/
│   ├── themes/anchor/          # Active theme (hardcoded)
│   │   ├── components/         # Blade components
│   │   │   ├── app/            # Authenticated layout components
│   │   │   ├── elements/       # Root-level reusable elements
│   │   │   ├── layouts/        # App & marketing layouts
│   │   │   └── marketing/      # Marketing page components
│   │   ├── pages/              # Folio file-based routes
│   │   │   ├── settings/       # Settings pages (profile, security, api, etc.)
│   │   │   ├── changelog/      # Changelog pages
│   │   │   ├── subscription/   # Subscription management
│   │   │   └── layout/         # Shared page layouts
│   │   ├── livewire/           # Volt single-file components
│   │   ├── emails/             # Email templates
│   │   └── assets/             # CSS and JS files
│   └── views/                  # App-level views
│       ├── auth/               # Auth pages
│       ├── components/         # App root components
│       ├── filament/           # Filament overrides/widgets
│       └── livewire/           # Livewire views
├── routes/
│   ├── web.php                 # Calls Wave::routes()
│   ├── api.php                 # Calls Wave::api()
│   └── console.php             # Scheduled commands
├── config/                     # Configuration files
│   ├── wave.php                # Main Wave config
│   ├── activity.php            # Activity logging
│   ├── limits.php              # Feature limits per plan
│   └── *.php                   # Standard Laravel configs
├── database/
│   ├── migrations/             # App-specific migrations
│   ├── seeders/                # Database seeders
│   └── factories/              # Model factories
├── tests/
│   ├── Feature/                # Feature/integration tests
│   ├── Unit/                   # Unit tests
│   └── Datasets/               # Pest datasets
├── bootstrap/
│   ├── app.php                 # Laravel 12 bootstrap (middleware, routes)
│   └── providers.php           # Service providers list
├── public/                     # Web root
├── storage/                    # Logs, cache, uploads
├── composer.json               # PHP dependencies
└── package.json                # Frontend dependencies
```

## Directory Purposes

**`app/Filament/Resources/`:**
- Purpose: Admin CRUD interfaces
- Contains: Resource classes with form schemas, table definitions, relation managers
- Key resources: `Users/`, `Plans/`, `Subscriptions/`, `Organizations/`, `Invoices/`, `Coupons/`, `Transactions/`, `PromotionCodes/`, `Changelogs/`, `Roles/`, `Refunds/`
- Each resource has `Pages/` subdirectory (Create, Edit, List)

**`wave/src/`:**
- Purpose: Core Wave framework (billing, subscriptions, plans, features)
- Contains: Models at root level, organized subdirectories for HTTP, traits, etc.
- Key files: `WaveServiceProvider.php`, `User.php`, `Plan.php`, `Subscription.php`, `Invoice.php`

**`resources/themes/anchor/`:**
- Purpose: Active theme with all user-facing UI
- Contains: Blade components, Folio pages, Volt components, email templates, assets
- Registration: `WaveServiceProvider` registers view namespace (`theme::`), component paths, Folio directory

**`resources/themes/anchor/components/elements/`:**
- Purpose: Reusable UI primitives registered as root-level anonymous components
- Contains: `button.blade.php`, `input.blade.php`, `checkbox.blade.php`, `link.blade.php`, `icon.blade.php`, etc.
- Usage: `<x-button>`, `<x-input>` (no namespace prefix needed)

## Key File Locations

**Entry Points:**
- `bootstrap/app.php` - Application bootstrap (Laravel 12 format)
- `routes/web.php` - Web route registration (delegates to Wave)
- `routes/api.php` - API route registration (delegates to Wave)
- `routes/console.php` - Scheduled command registration

**Configuration:**
- `config/wave.php` - Main Wave settings (billing, colors, features)
- `config/activity.php` - Activity logging
- `config/limits.php` - Feature limits per plan
- `.env.example` - Environment variable template
- `bootstrap/providers.php` - Service provider list

**Core Logic:**
- `wave/src/WaveServiceProvider.php` - Framework registration
- `wave/src/User.php` - Base user model with billing
- `wave/src/Subscription.php` - Subscription model (extends Cashier)
- `wave/src/Services/PlanChangeResolver.php` - Plan change logic
- `app/Listeners/HandleStripeWebhook.php` - Webhook processing

**Testing:**
- `tests/Feature/` - Feature tests
- `tests/Datasets/` - Parameterized test data
- `tests/Pest.php` - Pest configuration
- `phpunit.xml` - Test runner configuration

## Naming Conventions

**Files:**
- PascalCase for PHP classes: `User.php`, `SubscriptionController.php`
- kebab-case for Blade components: `user-menu.blade.php`, `org-switcher.blade.php`
- Timestamp prefix for migrations: `2026_02_15_000001_create_invoices_table.php`
- `{Model}Factory.php` for factories: `UserFactory.php`, `InvoiceFactory.php`
- `{Model}Policy.php` for policies: `UserPolicy.php`

**Directories:**
- PascalCase for PHP namespaces: `Filament/Resources/`, `Http/Controllers/`
- kebab-case for theme directories: `resources/themes/anchor/`
- Plural for collections: `Models/`, `Listeners/`, `Commands/`

**Special Patterns:**
- `{Model}Resource.php` for Filament resources
- `{Action}Controller.php` for controllers
- `Has{Capability}.php` for traits
- `{Event}Listener.php` or descriptive name for listeners

## Where to Add New Code

**New Feature (billing-related):**
- Model: `wave/src/{Model}.php` (framework) or `app/Models/{Model}.php` (app)
- Migration: `wave/database/migrations/` (framework) or `database/migrations/` (app)
- Controller: `wave/src/Http/Controllers/{Feature}.php`
- Routes: `wave/routes/web.php` or `wave/routes/api.php`
- Tests: `tests/Feature/{Feature}Test.php`

**New Admin Resource:**
- Resource: `app/Filament/Resources/{Model}/{Model}Resource.php`
- Pages: `app/Filament/Resources/{Model}/Pages/` (Create, Edit, List)
- Relation managers: `app/Filament/Resources/{Model}/RelationManagers/`

**New User-Facing Page:**
- Folio route: `resources/themes/anchor/pages/{route}.blade.php`
- Component: `resources/themes/anchor/components/{name}.blade.php`
- Livewire/Volt: `resources/themes/anchor/livewire/{name}.blade.php`
- Layout: Use `<x-layouts.app>` (authenticated) or `<x-layouts.marketing>` (public)

**New Console Command:**
- Create: `wave/src/Console/Commands/{Command}.php` or `app/Console/Commands/{Command}.php`
- Auto-discovered by Laravel 12
- Schedule: Add to `routes/console.php`

**New Helper Function:**
- Create: `wave/src/Helpers/{name}.php`
- Auto-loaded by `WaveServiceProvider::loadHelpers()`

## Special Directories

**`wave/`:**
- Purpose: Core Wave framework (treated as internal package)
- Source: Part of this repository (not a Composer package)
- Committed: Yes

**`resources/themes/anchor/`:**
- Purpose: Hardcoded active theme (theme switching removed)
- Source: Manually maintained
- Committed: Yes

**`storage/`:**
- Purpose: Logs, cache, compiled views, uploads
- Source: Generated at runtime
- Committed: No (gitignored, except `storage/app/.gitkeep`)

---

*Structure analysis: 2026-02-26*
*Update when directory structure changes*
