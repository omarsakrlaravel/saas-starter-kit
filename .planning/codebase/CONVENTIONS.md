# Coding Conventions

**Analysis Date:** 2026-02-26

## Naming Patterns

**Files:**
- PascalCase for PHP classes: `User.php`, `HandleStripeWebhook.php`
- kebab-case for Blade components: `alert.blade.php`, `user-menu.blade.php`
- Timestamp_snake_case for migrations: `2026_02_15_000001_create_invoices_table.php`
- `{Model}Factory.php` for factories, `{Model}Policy.php` for policies

**Functions:**
- camelCase for all methods: `getActivePlans()`, `initializeSeatQuantity()`, `clearUserCache()`
- `handle()` for listeners, actions, jobs
- `mount()`, `boot()` for Livewire lifecycle

**Variables:**
- camelCase for PHP variables
- snake_case for database columns: `deletion_scheduled_at`, `billable_type`, `stripe_id`
- SCREAMING_SNAKE_CASE for constants: `protected const HOME = '/dashboard'`

**Types:**
- PascalCase for classes, interfaces, traits
- `Has{Capability}` prefix for traits: `HasPlanFeatures`, `HasProfileKeyValues`
- No `I` prefix on interfaces

## Code Style

**Formatting:**
- Laravel Pint with Laravel preset (`pint.json`)
- 4 spaces indentation (`.editorconfig`)
- LF line endings, UTF-8 charset
- Trim trailing whitespace
- Run: `vendor/bin/pint --dirty --format agent`

**PHP Version Features:**
- PHP 8.4 constructor property promotion used
- Explicit return type declarations on all methods
- `match()` expressions where appropriate
- Named arguments for clarity

**Linting:**
- Laravel Pint as sole formatter/linter
- Must run before finalizing changes

## Import Organization

**Order:**
1. PHP built-in classes (`Exception`, `ReflectionMethod`)
2. Laravel/Illuminate classes
3. Third-party packages (`Stripe`, `Spatie`, etc.)
4. Wave framework classes
5. App-specific classes

**Pattern:**
- Full namespace imports for all classes
- No aliases unless resolving conflicts
- Grouped logically by domain

## Error Handling

**Patterns:**
- Try-catch for external service calls (Stripe API)
- Graceful fallbacks when cache unavailable (return direct query)
- Silent catch with no logging in cache operations (`catch (Exception) {}`)
- Form Request validation at HTTP boundary
- Middleware-based authorization checks

**Error Types:**
- Throw on validation failures, unauthorized access
- Return empty collections on external API failures (e.g., `billingInvoices()`)
- Log and continue for webhook processing errors

## Logging

**Framework:**
- Laravel `Log` facade
- Activity logging via `wave/src/ActivityLog.php`
- Queued logging supported via `config/activity.php`

**Patterns:**
- Log significant actions: login, logout, subscription changes
- Webhook processing logged with event details
- No `dd()` or `dump()` in committed code (remove before commit)

## Comments

**When to Comment:**
- PHPDoc blocks for model properties and relationships
- `@return` type hints with array shapes where helpful
- No inline comments unless logic is complex
- Self-documenting code preferred

**PHPDoc:**
- Required on model relationship methods: `@return HasMany`, `@return MorphTo`
- Array shape annotations: `@return array<string, string>`
- Factory states documented with method-level PHPDoc

**TODO Comments:**
- Format: `// TODO: description`
- Remove debug comments (`dd()`, `dump()`) before committing

## Function Design

**Size:**
- Keep methods focused on single responsibility
- Extract complex logic into dedicated Action or Service classes

**Parameters:**
- Use type hints on all parameters
- Constructor property promotion for dependency injection
- Options arrays or dedicated config objects for 4+ parameters

**Return Values:**
- Explicit return type declarations always
- Return early for guard clauses
- Nullable returns where appropriate: `?Plan`, `?Subscription`

## Model Design

**Relationships:**
- Explicit return type hints: `public function invoices(): HasMany`
- Polymorphic relationships for billing: `morphMany()`, `morphTo()`

**Casts:**
- Define via `protected function casts(): array` method (Laravel 11+ style)
- DateTime casts: `'deletion_scheduled_at' => 'datetime'`
- Integer casts for monetary values: `'amount_due' => 'integer'`
- Array casts for JSON columns: `'notification_preferences' => 'array'`

**Mass Assignment:**
- `protected $guarded = []` for most models
- Explicit `$fillable` arrays for more restrictive models

**Factories:**
- States as fluent methods: `->paid()`, `->open()`, `->void()`
- Default `definition()` returns full attribute array
- Nested factories for relationships: `'billable_id' => User::factory()`

## Migration Design

- Anonymous class style: `return new class() extends Migration`
- Explicit closure types: `function (Blueprint $table): void`
- Proper constraints: `->unique()`, `->index()`, `->nullable()`
- Foreign keys with cascade behavior defined
- Composite indexes for polymorphic relationships

## Blade Components

- Props declared at top: `@props(['title' => '', 'type' => 'gray'])`
- Alpine.js for client-side interactivity: `x-data`, `x-show`, `@click`
- Conditional classes via Tailwind Merge
- Anonymous components for reusable UI elements
- Slots for content flexibility

## Filament Resources

- 2/3 + 1/3 column layout: `->columns(3)` with left `->columnSpan(2)`, right `->columnSpan(1)`
- All fields wrapped in Sections (never bare at root)
- Left Group for main content, right Group for status/actions
- Simple resources use single full-width Section with `->columnSpanFull()`

## Livewire Components

- Class-based Livewire 3 in `wave/src/Http/Livewire/`
- Volt single-file components in `resources/themes/anchor/livewire/`
- Traits for shared behavior: `EnsuresBillingContextAccess`
- Public properties for reactivity
- Type hints on all method parameters and returns

---

*Convention analysis: 2026-02-26*
*Update when patterns change*
