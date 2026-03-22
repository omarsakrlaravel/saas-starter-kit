<?php

use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\PromotionCode;
use App\Models\Subscription;
use App\Models\User;

use function Pest\Laravel\artisan;

// ──────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────

function fakeStripeCollection(array $items): object
{
    return new class($items)
    {
        public array $data;

        public function __construct(array $items)
        {
            $this->data = $items;
        }

        public function autoPagingIterator(): ArrayIterator
        {
            return new ArrayIterator($this->data);
        }
    };
}

function fakeStripeObject(array $data): object
{
    return json_decode(json_encode($data));
}

function bindMockStripe(object $mockStripe): void
{
    app()->instance(\Stripe\StripeClient::class, $mockStripe);
}

// ──────────────────────────────────────────────
// Setup / Teardown
// ──────────────────────────────────────────────

beforeEach(function () {
    PromotionCode::query()->delete();
    Coupon::query()->delete();
    Invoice::query()->delete();
});

afterEach(function () {
    app()->forgetInstance(\Stripe\StripeClient::class);
    PromotionCode::query()->delete();
    Coupon::query()->delete();
    Invoice::query()->delete();
});

// ──────────────────────────────────────────────
// stripe:sync-invoices
// ──────────────────────────────────────────────

test('sync invoices creates new invoices from Stripe data', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_sinv_001']);
    $plan = Plan::first() ?? Plan::create([
        'name' => 'Starter',
        'description' => 'Starter plan',
        'features' => 'Feature 1',
        'monthly_price' => '10.00',
        'yearly_price' => '100.00',
        'monthly_price_id' => 'price_starter_monthly',
        'yearly_price_id' => 'price_starter_yearly',
        'active' => true,
    ]);
    $subscription = Subscription::create([
        'user_id' => $user->id,
        'type' => 'default',
        'billable_type' => 'user',
        'billable_id' => $user->id,
        'plan_id' => $plan->id,
        'stripe_id' => 'sub_sinv_001',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_monthly',
        'quantity' => 1,
    ]);

    $stripeInvoice = fakeStripeObject([
        'id' => 'in_sinv_001',
        'customer' => 'cus_sinv_001',
        'number' => 'INV-0001',
        'status' => 'paid',
        'currency' => 'usd',
        'amount_due' => 999,
        'amount_paid' => 999,
        'amount_remaining' => 0,
        'subtotal' => 999,
        'tax' => null,
        'total' => 999,
        'period_start' => 1700000000,
        'period_end' => 1702592000,
        'due_date' => null,
        'subscription' => 'sub_sinv_001',
        'status_transitions' => ['paid_at' => 1700000100],
        'hosted_invoice_url' => 'https://stripe.test/invoice/in_sinv_001',
        'invoice_pdf' => 'https://stripe.test/invoice/in_sinv_001.pdf',
        'lines' => ['data' => [['description' => 'Test Plan', 'amount' => 999]]],
    ]);

    $mockStripe = Mockery::mock(\Stripe\StripeClient::class);
    $mockInvoicesService = Mockery::mock();
    $mockInvoicesService->shouldReceive('all')
        ->with(['customer' => 'cus_sinv_001', 'limit' => 100])
        ->andReturn(fakeStripeCollection([$stripeInvoice]));
    $mockStripe->invoices = $mockInvoicesService;
    bindMockStripe($mockStripe);

    artisan('stripe:sync-invoices', ['--customer' => 'cus_sinv_001'])
        ->assertSuccessful()
        ->expectsOutputToContain('Synced 1 invoices (1 created, 0 updated)');

    $invoice = Invoice::where('stripe_id', 'in_sinv_001')->first();
    expect($invoice)->not->toBeNull()
        ->and($invoice->stripe_customer_id)->toBe('cus_sinv_001')
        ->and($invoice->billable_type)->toBe('user')
        ->and($invoice->billable_id)->toBe($user->id)
        ->and($invoice->subscription_id)->toBe($subscription->id)
        ->and($invoice->number)->toBe('INV-0001')
        ->and($invoice->status)->toBe('paid')
        ->and($invoice->amount_due)->toBe(999)
        ->and($invoice->amount_paid)->toBe(999)
        ->and($invoice->total)->toBe(999)
        ->and($invoice->hosted_invoice_url)->toBe('https://stripe.test/invoice/in_sinv_001')
        ->and($invoice->invoice_pdf)->toBe('https://stripe.test/invoice/in_sinv_001.pdf')
        ->and($invoice->paid_at)->not->toBeNull()
        ->and($invoice->line_items)->toBeArray()->toHaveCount(1);

    $subscription->delete();
    $user->forceDelete();
});

test('sync invoices updates existing invoices', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_sinv_002']);

    Invoice::create([
        'stripe_id' => 'in_sinv_002',
        'stripe_customer_id' => 'cus_sinv_002',
        'billable_type' => 'user',
        'billable_id' => $user->id,
        'number' => 'INV-0002',
        'status' => 'open',
        'currency' => 'usd',
        'amount_due' => 500,
        'amount_paid' => 0,
        'amount_remaining' => 500,
        'subtotal' => 500,
        'total' => 500,
    ]);

    $stripeInvoice = fakeStripeObject([
        'id' => 'in_sinv_002',
        'customer' => 'cus_sinv_002',
        'number' => 'INV-0002',
        'status' => 'paid',
        'currency' => 'usd',
        'amount_due' => 500,
        'amount_paid' => 500,
        'amount_remaining' => 0,
        'subtotal' => 500,
        'tax' => null,
        'total' => 500,
        'period_start' => 1700000000,
        'period_end' => 1702592000,
        'due_date' => null,
        'subscription' => null,
        'status_transitions' => ['paid_at' => 1700000200],
        'hosted_invoice_url' => null,
        'invoice_pdf' => null,
        'lines' => ['data' => []],
    ]);

    $mockStripe = Mockery::mock(\Stripe\StripeClient::class);
    $mockInvoicesService = Mockery::mock();
    $mockInvoicesService->shouldReceive('all')
        ->andReturn(fakeStripeCollection([$stripeInvoice]));
    $mockStripe->invoices = $mockInvoicesService;
    bindMockStripe($mockStripe);

    artisan('stripe:sync-invoices', ['--customer' => 'cus_sinv_002'])
        ->assertSuccessful()
        ->expectsOutputToContain('0 created, 1 updated');

    $invoice = Invoice::where('stripe_id', 'in_sinv_002')->first();
    expect($invoice->status)->toBe('paid')
        ->and($invoice->amount_paid)->toBe(500)
        ->and($invoice->amount_remaining)->toBe(0);

    $user->forceDelete();
});

test('sync invoices warns when no users have stripe ids', function () {
    $mockStripe = Mockery::mock(\Stripe\StripeClient::class);
    bindMockStripe($mockStripe);

    $originalIds = User::query()->whereNotNull('stripe_id')->where('stripe_id', '!=', '')->pluck('stripe_id', 'id');
    User::query()->whereNotNull('stripe_id')->update(['stripe_id' => null]);

    artisan('stripe:sync-invoices')
        ->assertSuccessful()
        ->expectsOutputToContain('No users with a Stripe customer ID found');

    foreach ($originalIds as $id => $stripeId) {
        User::where('id', $id)->update(['stripe_id' => $stripeId]);
    }
});

// ──────────────────────────────────────────────
// stripe:sync-coupons
// ──────────────────────────────────────────────

test('sync coupons creates new coupons and promotion codes', function () {
    $stripeCoupon = fakeStripeObject([
        'id' => 'coupon_sc_001',
        'name' => '20% Off',
        'amount_off' => null,
        'percent_off' => 20.00,
        'currency' => null,
        'duration' => 'forever',
        'duration_in_months' => null,
        'max_redemptions' => null,
        'times_redeemed' => 5,
        'valid' => true,
        'redeem_by' => null,
        'metadata' => null,
    ]);

    $stripePromoCode = fakeStripeObject([
        'id' => 'promo_sc_001',
        'coupon' => ['id' => 'coupon_sc_001'],
        'code' => 'SAVE20',
        'active' => true,
        'max_redemptions' => 100,
        'times_redeemed' => 3,
        'restrictions' => [
            'first_time_transaction' => true,
            'minimum_amount' => 5000,
            'minimum_amount_currency' => 'usd',
        ],
        'expires_at' => 1735689600,
        'metadata' => null,
    ]);

    $mockStripe = Mockery::mock(\Stripe\StripeClient::class);
    $mockCouponsService = Mockery::mock();
    $mockCouponsService->shouldReceive('all')
        ->with(['limit' => 100])
        ->andReturn(fakeStripeCollection([$stripeCoupon]));
    $mockStripe->coupons = $mockCouponsService;
    $mockPromoCodesService = Mockery::mock();
    $mockPromoCodesService->shouldReceive('all')
        ->with(['limit' => 100])
        ->andReturn(fakeStripeCollection([$stripePromoCode]));
    $mockStripe->promotionCodes = $mockPromoCodesService;
    bindMockStripe($mockStripe);

    artisan('stripe:sync-coupons')
        ->assertSuccessful()
        ->expectsOutputToContain('1 coupons (1 created, 0 updated)')
        ->expectsOutputToContain('1 promotion codes (1 created, 0 updated)');

    $coupon = Coupon::where('stripe_id', 'coupon_sc_001')->first();
    expect($coupon)->not->toBeNull()
        ->and($coupon->name)->toBe('20% Off')
        ->and((float) $coupon->percent_off)->toBe(20.00)
        ->and($coupon->duration)->toBe('forever')
        ->and($coupon->times_redeemed)->toBe(5)
        ->and($coupon->active)->toBeTrue()
        ->and($coupon->valid)->toBeTrue();

    $promoCode = PromotionCode::where('stripe_id', 'promo_sc_001')->first();
    expect($promoCode)->not->toBeNull()
        ->and($promoCode->code)->toBe('SAVE20')
        ->and($promoCode->coupon_id)->toBe($coupon->id)
        ->and($promoCode->active)->toBeTrue()
        ->and($promoCode->max_redemptions)->toBe(100)
        ->and($promoCode->times_redeemed)->toBe(3)
        ->and($promoCode->first_time_transaction)->toBeTrue()
        ->and($promoCode->minimum_amount)->toBe(5000)
        ->and($promoCode->minimum_amount_currency)->toBe('usd')
        ->and($promoCode->expires_at)->not->toBeNull();
});

test('sync coupons updates existing coupons', function () {
    Coupon::create([
        'stripe_id' => 'coupon_sc_002',
        'name' => 'Old Name',
        'percent_off' => 10.00,
        'duration' => 'once',
        'times_redeemed' => 0,
        'active' => true,
        'valid' => true,
    ]);

    $stripeCoupon = fakeStripeObject([
        'id' => 'coupon_sc_002',
        'name' => 'Updated Name',
        'amount_off' => null,
        'percent_off' => 10.00,
        'currency' => null,
        'duration' => 'once',
        'duration_in_months' => null,
        'max_redemptions' => null,
        'times_redeemed' => 12,
        'valid' => false,
        'redeem_by' => null,
        'metadata' => null,
    ]);

    $mockStripe = Mockery::mock(\Stripe\StripeClient::class);
    $mockCouponsService = Mockery::mock();
    $mockCouponsService->shouldReceive('all')->andReturn(fakeStripeCollection([$stripeCoupon]));
    $mockStripe->coupons = $mockCouponsService;
    $mockPromoCodesService = Mockery::mock();
    $mockPromoCodesService->shouldReceive('all')->andReturn(fakeStripeCollection([]));
    $mockStripe->promotionCodes = $mockPromoCodesService;
    bindMockStripe($mockStripe);

    artisan('stripe:sync-coupons')
        ->assertSuccessful()
        ->expectsOutputToContain('0 created, 1 updated');

    $coupon = Coupon::where('stripe_id', 'coupon_sc_002')->first();
    expect($coupon->name)->toBe('Updated Name')
        ->and($coupon->times_redeemed)->toBe(12)
        ->and($coupon->valid)->toBeFalse()
        ->and($coupon->active)->toBeFalse();
});

test('sync coupons skips promo code when local coupon is missing', function () {
    $stripePromoCode = fakeStripeObject([
        'id' => 'promo_orphan_sc',
        'coupon' => ['id' => 'coupon_nonexistent'],
        'code' => 'ORPHAN',
        'active' => true,
        'max_redemptions' => null,
        'times_redeemed' => 0,
        'restrictions' => null,
        'expires_at' => null,
        'metadata' => null,
    ]);

    $mockStripe = Mockery::mock(\Stripe\StripeClient::class);
    $mockCouponsService = Mockery::mock();
    $mockCouponsService->shouldReceive('all')->andReturn(fakeStripeCollection([]));
    $mockStripe->coupons = $mockCouponsService;
    $mockPromoCodesService = Mockery::mock();
    $mockPromoCodesService->shouldReceive('all')->andReturn(fakeStripeCollection([$stripePromoCode]));
    $mockStripe->promotionCodes = $mockPromoCodesService;
    bindMockStripe($mockStripe);

    artisan('stripe:sync-coupons')
        ->assertSuccessful();

    expect(PromotionCode::where('stripe_id', 'promo_orphan_sc')->exists())->toBeFalse();
});

// ──────────────────────────────────────────────
// stripe:sync-all
// ──────────────────────────────────────────────

test('sync all calls both sync commands', function () {
    $mockStripe = Mockery::mock(\Stripe\StripeClient::class);
    $mockCouponsService = Mockery::mock();
    $mockCouponsService->shouldReceive('all')->andReturn(fakeStripeCollection([]));
    $mockStripe->coupons = $mockCouponsService;
    $mockPromoCodesService = Mockery::mock();
    $mockPromoCodesService->shouldReceive('all')->andReturn(fakeStripeCollection([]));
    $mockStripe->promotionCodes = $mockPromoCodesService;
    bindMockStripe($mockStripe);

    $originalIds = User::query()->whereNotNull('stripe_id')->where('stripe_id', '!=', '')->pluck('stripe_id', 'id');
    User::query()->whereNotNull('stripe_id')->update(['stripe_id' => null]);

    artisan('stripe:sync-all')
        ->assertSuccessful()
        ->expectsOutputToContain('All Stripe data synced successfully');

    foreach ($originalIds as $id => $stripeId) {
        User::where('id', $id)->update(['stripe_id' => $stripeId]);
    }
});

// ──────────────────────────────────────────────
// Idempotency
// ──────────────────────────────────────────────

test('sync invoices is idempotent when run multiple times', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_sidem']);

    $stripeInvoice = fakeStripeObject([
        'id' => 'in_sidem_001',
        'customer' => 'cus_sidem',
        'number' => 'INV-IDEM',
        'status' => 'paid',
        'currency' => 'usd',
        'amount_due' => 1500,
        'amount_paid' => 1500,
        'amount_remaining' => 0,
        'subtotal' => 1500,
        'tax' => null,
        'total' => 1500,
        'period_start' => 1700000000,
        'period_end' => 1702592000,
        'due_date' => null,
        'subscription' => null,
        'status_transitions' => ['paid_at' => null],
        'hosted_invoice_url' => null,
        'invoice_pdf' => null,
        'lines' => ['data' => []],
    ]);

    $mockStripe = Mockery::mock(\Stripe\StripeClient::class);
    $mockInvoicesService = Mockery::mock();
    $mockInvoicesService->shouldReceive('all')->andReturn(fakeStripeCollection([$stripeInvoice]));
    $mockStripe->invoices = $mockInvoicesService;
    bindMockStripe($mockStripe);

    artisan('stripe:sync-invoices', ['--customer' => 'cus_sidem'])->assertSuccessful();
    artisan('stripe:sync-invoices', ['--customer' => 'cus_sidem'])->assertSuccessful();

    expect(Invoice::where('stripe_id', 'in_sidem_001')->count())->toBe(1);

    $user->forceDelete();
});

test('sync coupons is idempotent when run multiple times', function () {
    $stripeCoupon = fakeStripeObject([
        'id' => 'coupon_sidem_001',
        'name' => 'Idempotent Coupon',
        'amount_off' => 500,
        'percent_off' => null,
        'currency' => 'usd',
        'duration' => 'once',
        'duration_in_months' => null,
        'max_redemptions' => null,
        'times_redeemed' => 0,
        'valid' => true,
        'redeem_by' => null,
        'metadata' => null,
    ]);

    $mockStripe = Mockery::mock(\Stripe\StripeClient::class);
    $mockCouponsService = Mockery::mock();
    $mockCouponsService->shouldReceive('all')->andReturn(fakeStripeCollection([$stripeCoupon]));
    $mockStripe->coupons = $mockCouponsService;
    $mockPromoCodesService = Mockery::mock();
    $mockPromoCodesService->shouldReceive('all')->andReturn(fakeStripeCollection([]));
    $mockStripe->promotionCodes = $mockPromoCodesService;
    bindMockStripe($mockStripe);

    artisan('stripe:sync-coupons')->assertSuccessful();
    artisan('stripe:sync-coupons')->assertSuccessful();

    expect(Coupon::where('stripe_id', 'coupon_sidem_001')->count())->toBe(1);
});
