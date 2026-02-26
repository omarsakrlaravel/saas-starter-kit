# Testing Patterns

**Analysis Date:** 2026-02-26

## Test Framework

**Runner:**
- Pest v4 (PHPUnit 12 underneath)
- Config: `phpunit.xml` in project root

**Assertion Library:**
- Pest built-in `expect()` with chaining
- Laravel testing helpers (`actingAs`, `get`, `post`)
- Livewire/Volt testing via `pestphp/pest-plugin-livewire`

**Run Commands:**
```bash
php artisan test --compact                    # Run all tests
php artisan test --compact --filter=testName  # Filter by name
php artisan test tests/Feature/SpecificTest.php  # Single file
vendor/bin/pest                               # Direct Pest runner
```

## Test File Organization

**Location:**
- Feature tests: `tests/Feature/*.php`
- Unit tests: `tests/Unit/*.php`
- Datasets: `tests/Datasets/*.php`

**Naming:**
- `{Feature}Test.php` for feature tests: `RouteTest.php`, `BillingModelsTest.php`
- `{Feature}Test.php` for unit tests (same convention)

**Structure:**
```
tests/
├── Feature/
│   ├── RouteTest.php
│   ├── BillingModelsTest.php
│   ├── PasswordValidationTest.php
│   ├── UserResourceTest.php
│   ├── StripeSyncCommandsTest.php
│   └── ...
├── Unit/
│   └── ExampleTest.php
├── Datasets/
│   ├── Routes.php
│   └── AuthRoutes.php
├── Pest.php          # Global Pest config
└── TestCase.php      # Base test class
```

## Test Structure

**Suite Organization:**
```php
// Pest style with it() and test()
it('invoice can be created via factory', function () {
    $invoice = Invoice::factory()->create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
    ]);

    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->exists)->toBeTrue()
        ->and($invoice->stripe_id)->toStartWith('in_');
});

test('invoice casts date fields correctly', function () {
    $invoice = Invoice::factory()->create();
    expect($invoice->paid_at)->toBeInstanceOf(Carbon::class);
});
```

**Patterns:**
- `beforeEach()` for per-test setup (creating test user)
- `afterEach()` for cleanup (deleting models)
- Pest expectation chaining: `expect($x)->condition1->and($y)->condition2`
- Custom expectations in `tests/Pest.php`: `expect()->extend('toBeOne', ...)`

## Mocking

**Framework:**
- Mockery v1.6 (available but used sparingly)
- Laravel's built-in fakes for services

**Patterns:**
- `actingAs($user)` for authenticated requests
- Factory states for different model configurations
- Database-backed tests (no mocking Eloquent)

**What to Mock:**
- External API calls (Stripe)
- Queue jobs in sync mode (`QUEUE_CONNECTION=sync` in tests)

**What NOT to Mock:**
- Eloquent relationships and queries
- Internal business logic
- Cache (set to `array` driver in tests)

## Fixtures and Factories

**Test Data:**
```php
// Factory usage
$user = User::factory()->create();
$invoice = Invoice::factory()->paid()->create([
    'billable_type' => 'user',
    'billable_id' => $user->id,
]);

// Factory states
Invoice::factory()->paid()->create();    // status=paid, amount_paid set
Invoice::factory()->open()->create();    // status=open
Invoice::factory()->void()->create();    // status=void
```

**Location:**
- Factories: `database/factories/*.php` (UserFactory, InvoiceFactory, etc.)
- Datasets: `tests/Datasets/Routes.php`, `tests/Datasets/AuthRoutes.php`

**Dataset-Driven Tests:**
```php
// tests/Datasets/Routes.php
dataset('routes', [
    ['/'],
    ['/dashboard'],
    ['/settings/profile'],
]);

// Usage in test
it('returns 200 for public routes')
    ->with('routes')
    ->expect(fn ($route) => get($route)->assertStatus(200));
```

## Coverage

**Requirements:**
- No enforced coverage target
- Focus on critical paths: billing, authentication, models

**Configuration:**
- PHPUnit coverage source: `./app` directory
- Test environment: `APP_ENV=testing`, `CACHE_STORE=array`, `QUEUE_CONNECTION=sync`

## Test Types

**Unit Tests:**
- Scope: Isolated function/class testing
- Location: `tests/Unit/`
- Minimal in this codebase

**Feature Tests:**
- Scope: HTTP requests, Livewire components, model integration
- Location: `tests/Feature/`
- Primary test type used

**Browser Tests:**
- Framework: Laravel Dusk v8 (available)
- Location: `tests/Browser/` (if present)

## Common Patterns

**HTTP Route Testing:**
```php
use function Pest\Laravel\get;

it('returns 200 for dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/dashboard')->assertStatus(200);
});
```

**Volt Component Testing:**
```php
use Livewire\Volt\Volt;

it('validates password length', function () {
    Volt::test('settings.security')
        ->set('data.current_password', 'oldpassword123')
        ->set('data.password', 'short')
        ->call('save')
        ->assertHasErrors(['data.password']);
});
```

**Model Relationship Testing:**
```php
it('has correct relationship return type', function () {
    $reflection = new ReflectionMethod(User::class, 'localInvoices');
    expect($reflection->getReturnType()?->getName())
        ->toBe('Illuminate\Database\Eloquent\Relations\MorphMany');
});
```

**Factory State Testing:**
```php
it('paid state sets correct attributes', function () {
    $invoice = Invoice::factory()->paid()->create();
    expect($invoice->status)->toBe('paid')
        ->and($invoice->amount_paid)->toBe($invoice->total)
        ->and($invoice->paid_at)->not->toBeNull();
});
```

## Test Database Configuration

```xml
<!-- phpunit.xml -->
<env name="DB_CONNECTION" value="mysql"/>
<env name="DB_DATABASE" value="saas-starter-kit-pest"/>
<env name="APP_ENV" value="testing"/>
<env name="CACHE_STORE" value="array"/>
<env name="QUEUE_CONNECTION" value="sync"/>
```

---

*Testing analysis: 2026-02-26*
*Update when test patterns change*
