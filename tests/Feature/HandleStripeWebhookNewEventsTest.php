<?php

use App\Listeners\HandleStripeWebhook;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\PromotionCode;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Events\WebhookReceived;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();

    $this->listener = app(HandleStripeWebhook::class);
    $this->user = User::factory()->create(['stripe_id' => 'cus_test_'.uniqid()]);

    $this->plan = Plan::query()->first();

    if (! $this->plan) {
        $this->plan = Plan::create([
            'name' => 'Premium',
            'description' => 'Premium test plan',
            'features' => 'Feature 1, Feature 2',
            'monthly_price' => '10.00',
            'yearly_price' => '100.00',
            'monthly_price_id' => 'price_premium_monthly',
            'yearly_price_id' => 'price_premium_yearly',
            'active' => true,
        ]);
    }
});

// ──────────────────────────────────────────────────────────────
// Invoice events
// ──────────────────────────────────────────────────────────────

test('invoice.created creates a local invoice record', function () {
    $stripeInvoiceId = 'in_'.uniqid();

    $event = new WebhookReceived([
        'type' => 'invoice.created',
        'data' => [
            'object' => [
                'id' => $stripeInvoiceId,
                'customer' => $this->user->stripe_id,
                'subscription' => null,
                'number' => 'INV-001',
                'status' => 'draft',
                'currency' => 'usd',
                'amount_due' => 1000,
                'amount_paid' => 0,
                'amount_remaining' => 1000,
                'subtotal' => 1000,
                'tax' => 0,
                'total' => 1000,
                'period_start' => now()->subMonth()->timestamp,
                'period_end' => now()->timestamp,
                'due_date' => now()->addDays(30)->timestamp,
                'hosted_invoice_url' => 'https://stripe.com/invoice/123',
                'invoice_pdf' => 'https://stripe.com/invoice/123/pdf',
                'lines' => ['data' => []],
                'metadata' => ['key' => 'value'],
            ],
        ],
    ]);

    $this->listener->handle($event);

    $invoice = Invoice::query()->where('stripe_id', $stripeInvoiceId)->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->stripe_customer_id)->toBe($this->user->stripe_id)
        ->and($invoice->billable_type)->toBe('user')
        ->and($invoice->billable_id)->toBe($this->user->id)
        ->and($invoice->number)->toBe('INV-001')
        ->and($invoice->status)->toBe('draft')
        ->and($invoice->currency)->toBe('usd')
        ->and($invoice->amount_due)->toBe(1000)
        ->and($invoice->total)->toBe(1000);
})->group('stripe', 'billing');

test('invoice.updated updates an existing invoice record', function () {
    $stripeInvoiceId = 'in_'.uniqid();

    Invoice::query()->create([
        'stripe_id' => $stripeInvoiceId,
        'stripe_customer_id' => $this->user->stripe_id,
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'status' => 'draft',
        'currency' => 'usd',
        'amount_due' => 1000,
        'amount_paid' => 0,
        'amount_remaining' => 1000,
        'subtotal' => 1000,
        'total' => 1000,
    ]);

    $event = new WebhookReceived([
        'type' => 'invoice.updated',
        'data' => [
            'object' => [
                'id' => $stripeInvoiceId,
                'customer' => $this->user->stripe_id,
                'subscription' => null,
                'number' => 'INV-001',
                'status' => 'open',
                'currency' => 'usd',
                'amount_due' => 1000,
                'amount_paid' => 0,
                'amount_remaining' => 1000,
                'subtotal' => 1000,
                'tax' => 0,
                'total' => 1000,
                'period_start' => now()->timestamp,
                'period_end' => now()->addMonth()->timestamp,
                'lines' => ['data' => []],
                'metadata' => [],
            ],
        ],
    ]);

    $this->listener->handle($event);

    $invoice = Invoice::query()->where('stripe_id', $stripeInvoiceId)->first();

    expect($invoice->status)->toBe('open')
        ->and(Invoice::query()->where('stripe_id', $stripeInvoiceId)->count())->toBe(1);
})->group('stripe', 'billing');

test('invoice.paid sets paid_at and records coupon redemption', function () {
    $stripeInvoiceId = 'in_'.uniqid();
    $paidTimestamp = now()->timestamp;

    $coupon = Coupon::query()->create([
        'stripe_id' => 'coupon_test_'.uniqid(),
        'name' => '20% off',
        'percent_off' => 20.00,
        'duration' => 'once',
        'active' => true,
        'valid' => true,
    ]);

    $subscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'plan_id' => $this->plan->id,
        'stripe_id' => 'sub_test_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => $this->plan->monthly_price_id,
        'cycle' => 'month',
        'quantity' => 1,
    ]);

    $event = new WebhookReceived([
        'type' => 'invoice.paid',
        'data' => [
            'object' => [
                'id' => $stripeInvoiceId,
                'customer' => $this->user->stripe_id,
                'subscription' => $subscription->stripe_id,
                'number' => 'INV-002',
                'status' => 'paid',
                'currency' => 'usd',
                'amount_due' => 800,
                'amount_paid' => 800,
                'amount_remaining' => 0,
                'subtotal' => 1000,
                'tax' => 0,
                'total' => 800,
                'period_start' => now()->subMonth()->timestamp,
                'period_end' => now()->timestamp,
                'status_transitions' => ['paid_at' => $paidTimestamp],
                'discount' => [
                    'coupon' => [
                        'id' => $coupon->stripe_id,
                    ],
                    'promotion_code' => null,
                ],
                'lines' => ['data' => []],
                'metadata' => [],
            ],
        ],
    ]);

    $this->listener->handle($event);

    $invoice = Invoice::query()->where('stripe_id', $stripeInvoiceId)->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe('paid')
        ->and($invoice->paid_at)->not->toBeNull()
        ->and($invoice->subscription_id)->toBe($subscription->id);

    $redemption = CouponRedemption::query()->where('invoice_id', $invoice->id)->first();

    expect($redemption)->not->toBeNull()
        ->and($redemption->coupon_id)->toBe($coupon->id)
        ->and($redemption->discount_amount)->toBe(200)
        ->and($redemption->billable_type)->toBe('user')
        ->and($redemption->billable_id)->toBe($this->user->id);
})->group('stripe', 'billing');

test('invoice.payment_failed updates existing invoice status', function () {
    $stripeInvoiceId = 'in_'.uniqid();

    Invoice::query()->create([
        'stripe_id' => $stripeInvoiceId,
        'stripe_customer_id' => $this->user->stripe_id,
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'status' => 'open',
        'currency' => 'usd',
        'amount_due' => 1000,
        'amount_paid' => 0,
        'amount_remaining' => 1000,
        'subtotal' => 1000,
        'total' => 1000,
    ]);

    $event = new WebhookReceived([
        'type' => 'invoice.payment_failed',
        'data' => [
            'object' => [
                'id' => $stripeInvoiceId,
                'status' => 'open',
                'amount_remaining' => 1000,
            ],
        ],
    ]);

    $this->listener->handle($event);

    $invoice = Invoice::query()->where('stripe_id', $stripeInvoiceId)->first();
    expect($invoice->status)->toBe('open');
})->group('stripe', 'billing');

test('invoice.voided sets status to void', function () {
    $stripeInvoiceId = 'in_'.uniqid();

    Invoice::query()->create([
        'stripe_id' => $stripeInvoiceId,
        'stripe_customer_id' => $this->user->stripe_id,
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'status' => 'open',
        'currency' => 'usd',
        'amount_due' => 1000,
        'amount_paid' => 0,
        'amount_remaining' => 1000,
        'subtotal' => 1000,
        'total' => 1000,
    ]);

    $event = new WebhookReceived([
        'type' => 'invoice.voided',
        'data' => [
            'object' => [
                'id' => $stripeInvoiceId,
                'status' => 'void',
            ],
        ],
    ]);

    $this->listener->handle($event);

    $invoice = Invoice::query()->where('stripe_id', $stripeInvoiceId)->first();
    expect($invoice->status)->toBe('void');
})->group('stripe', 'billing');

// ──────────────────────────────────────────────────────────────
// Charge events (Transactions)
// ──────────────────────────────────────────────────────────────

test('charge.succeeded creates a transaction record', function () {
    $stripeChargeId = 'ch_'.uniqid();

    $event = new WebhookReceived([
        'type' => 'charge.succeeded',
        'data' => [
            'object' => [
                'id' => $stripeChargeId,
                'customer' => $this->user->stripe_id,
                'amount' => 2500,
                'currency' => 'usd',
                'status' => 'succeeded',
                'invoice' => null,
                'payment_method_details' => [
                    'type' => 'card',
                    'card' => [
                        'brand' => 'visa',
                        'last4' => '4242',
                    ],
                ],
                'failure_code' => null,
                'failure_message' => null,
                'amount_refunded' => 0,
                'description' => 'Subscription creation',
                'metadata' => [],
            ],
        ],
    ]);

    $this->listener->handle($event);

    $transaction = Transaction::query()->where('stripe_id', $stripeChargeId)->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction->amount)->toBe(2500)
        ->and($transaction->currency)->toBe('usd')
        ->and($transaction->status)->toBe('succeeded')
        ->and($transaction->payment_method_type)->toBe('card')
        ->and($transaction->payment_method_brand)->toBe('visa')
        ->and($transaction->payment_method_last4)->toBe('4242')
        ->and($transaction->billable_type)->toBe('user')
        ->and($transaction->billable_id)->toBe($this->user->id);
})->group('stripe', 'billing');

test('charge.failed creates a transaction with failure details', function () {
    $stripeChargeId = 'ch_'.uniqid();

    $event = new WebhookReceived([
        'type' => 'charge.failed',
        'data' => [
            'object' => [
                'id' => $stripeChargeId,
                'customer' => $this->user->stripe_id,
                'amount' => 1000,
                'currency' => 'usd',
                'status' => 'failed',
                'invoice' => null,
                'payment_method_details' => [
                    'type' => 'card',
                    'card' => [
                        'brand' => 'mastercard',
                        'last4' => '1234',
                    ],
                ],
                'failure_code' => 'card_declined',
                'failure_message' => 'Your card was declined.',
                'amount_refunded' => 0,
                'description' => null,
                'metadata' => [],
            ],
        ],
    ]);

    $this->listener->handle($event);

    $transaction = Transaction::query()->where('stripe_id', $stripeChargeId)->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction->status)->toBe('failed')
        ->and($transaction->failure_code)->toBe('card_declined')
        ->and($transaction->failure_message)->toBe('Your card was declined.');
})->group('stripe', 'billing');

test('charge.refunded updates transaction to refunded status', function () {
    $stripeChargeId = 'ch_'.uniqid();

    Transaction::query()->create([
        'stripe_id' => $stripeChargeId,
        'stripe_customer_id' => $this->user->stripe_id,
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'amount' => 2000,
        'currency' => 'usd',
        'status' => 'succeeded',
        'refunded_amount' => 0,
    ]);

    $event = new WebhookReceived([
        'type' => 'charge.refunded',
        'data' => [
            'object' => [
                'id' => $stripeChargeId,
                'customer' => $this->user->stripe_id,
                'amount' => 2000,
                'amount_refunded' => 2000,
                'currency' => 'usd',
                'status' => 'succeeded',
                'metadata' => [],
            ],
        ],
    ]);

    $this->listener->handle($event);

    $transaction = Transaction::query()->where('stripe_id', $stripeChargeId)->first();

    expect($transaction->status)->toBe('refunded')
        ->and($transaction->refunded_amount)->toBe(2000);
})->group('stripe', 'billing');

test('charge.refunded with partial refund sets partially_refunded status', function () {
    $stripeChargeId = 'ch_'.uniqid();

    Transaction::query()->create([
        'stripe_id' => $stripeChargeId,
        'stripe_customer_id' => $this->user->stripe_id,
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'amount' => 2000,
        'currency' => 'usd',
        'status' => 'succeeded',
        'refunded_amount' => 0,
    ]);

    $event = new WebhookReceived([
        'type' => 'charge.refunded',
        'data' => [
            'object' => [
                'id' => $stripeChargeId,
                'customer' => $this->user->stripe_id,
                'amount' => 2000,
                'amount_refunded' => 500,
                'currency' => 'usd',
                'status' => 'succeeded',
                'metadata' => [],
            ],
        ],
    ]);

    $this->listener->handle($event);

    $transaction = Transaction::query()->where('stripe_id', $stripeChargeId)->first();

    expect($transaction->status)->toBe('partially_refunded')
        ->and($transaction->refunded_amount)->toBe(500);
})->group('stripe', 'billing');

test('charge.updated updates an existing transaction', function () {
    $stripeChargeId = 'ch_'.uniqid();

    Transaction::query()->create([
        'stripe_id' => $stripeChargeId,
        'stripe_customer_id' => $this->user->stripe_id,
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'amount' => 1000,
        'currency' => 'usd',
        'status' => 'pending',
        'refunded_amount' => 0,
    ]);

    $event = new WebhookReceived([
        'type' => 'charge.updated',
        'data' => [
            'object' => [
                'id' => $stripeChargeId,
                'customer' => $this->user->stripe_id,
                'amount' => 1000,
                'currency' => 'usd',
                'status' => 'succeeded',
                'invoice' => null,
                'payment_method_details' => [
                    'type' => 'card',
                    'card' => [
                        'brand' => 'visa',
                        'last4' => '4242',
                    ],
                ],
                'failure_code' => null,
                'failure_message' => null,
                'amount_refunded' => 0,
                'description' => 'Updated description',
                'metadata' => [],
            ],
        ],
    ]);

    $this->listener->handle($event);

    $transaction = Transaction::query()->where('stripe_id', $stripeChargeId)->first();

    expect($transaction->status)->toBe('succeeded')
        ->and($transaction->description)->toBe('Updated description')
        ->and(Transaction::query()->where('stripe_id', $stripeChargeId)->count())->toBe(1);
})->group('stripe', 'billing');

// ──────────────────────────────────────────────────────────────
// Coupon events
// ──────────────────────────────────────────────────────────────

test('coupon.created creates a local coupon record', function () {
    $stripeCouponId = 'coupon_'.uniqid();

    $event = new WebhookReceived([
        'type' => 'coupon.created',
        'data' => [
            'object' => [
                'id' => $stripeCouponId,
                'name' => 'Summer Sale',
                'amount_off' => null,
                'percent_off' => 25.5,
                'currency' => null,
                'duration' => 'repeating',
                'duration_in_months' => 3,
                'max_redemptions' => 100,
                'times_redeemed' => 0,
                'valid' => true,
                'redeem_by' => now()->addMonths(6)->timestamp,
                'metadata' => ['campaign' => 'summer2026'],
            ],
        ],
    ]);

    $this->listener->handle($event);

    $coupon = Coupon::query()->where('stripe_id', $stripeCouponId)->first();

    expect($coupon)->not->toBeNull()
        ->and($coupon->name)->toBe('Summer Sale')
        ->and((float) $coupon->percent_off)->toBe(25.5)
        ->and($coupon->duration)->toBe('repeating')
        ->and($coupon->duration_in_months)->toBe(3)
        ->and($coupon->max_redemptions)->toBe(100)
        ->and($coupon->active)->toBeTrue()
        ->and($coupon->valid)->toBeTrue()
        ->and($coupon->redeem_by)->not->toBeNull();
})->group('stripe', 'billing');

test('coupon.updated updates an existing coupon', function () {
    $stripeCouponId = 'coupon_'.uniqid();

    Coupon::query()->create([
        'stripe_id' => $stripeCouponId,
        'name' => 'Old Name',
        'percent_off' => 10,
        'duration' => 'once',
        'active' => true,
        'valid' => true,
    ]);

    $event = new WebhookReceived([
        'type' => 'coupon.updated',
        'data' => [
            'object' => [
                'id' => $stripeCouponId,
                'name' => 'New Name',
                'percent_off' => 10,
                'duration' => 'once',
                'valid' => true,
                'times_redeemed' => 5,
                'metadata' => [],
            ],
        ],
    ]);

    $this->listener->handle($event);

    $coupon = Coupon::query()->where('stripe_id', $stripeCouponId)->first();

    expect($coupon->name)->toBe('New Name')
        ->and($coupon->times_redeemed)->toBe(5)
        ->and(Coupon::query()->where('stripe_id', $stripeCouponId)->count())->toBe(1);
})->group('stripe', 'billing');

test('coupon.deleted deactivates the local coupon', function () {
    $stripeCouponId = 'coupon_'.uniqid();

    Coupon::query()->create([
        'stripe_id' => $stripeCouponId,
        'name' => 'To Be Deleted',
        'percent_off' => 10,
        'duration' => 'once',
        'active' => true,
        'valid' => true,
    ]);

    $event = new WebhookReceived([
        'type' => 'coupon.deleted',
        'data' => [
            'object' => [
                'id' => $stripeCouponId,
            ],
        ],
    ]);

    $this->listener->handle($event);

    $coupon = Coupon::query()->where('stripe_id', $stripeCouponId)->first();

    expect($coupon)->not->toBeNull()
        ->and($coupon->active)->toBeFalse()
        ->and($coupon->valid)->toBeFalse();
})->group('stripe', 'billing');

// ──────────────────────────────────────────────────────────────
// Promotion code events
// ──────────────────────────────────────────────────────────────

test('promotion_code.created creates a local promotion code record', function () {
    $coupon = Coupon::query()->create([
        'stripe_id' => 'coupon_for_promo_'.uniqid(),
        'name' => 'Base Coupon',
        'percent_off' => 15,
        'duration' => 'once',
        'active' => true,
        'valid' => true,
    ]);

    $stripePromoId = 'promo_'.uniqid();

    $event = new WebhookReceived([
        'type' => 'promotion_code.created',
        'data' => [
            'object' => [
                'id' => $stripePromoId,
                'coupon' => ['id' => $coupon->stripe_id],
                'code' => 'SAVE15',
                'active' => true,
                'max_redemptions' => 50,
                'times_redeemed' => 0,
                'restrictions' => [
                    'first_time_transaction' => true,
                    'minimum_amount' => 5000,
                    'minimum_amount_currency' => 'usd',
                ],
                'expires_at' => now()->addMonth()->timestamp,
                'metadata' => [],
            ],
        ],
    ]);

    $this->listener->handle($event);

    $promoCode = PromotionCode::query()->where('stripe_id', $stripePromoId)->first();

    expect($promoCode)->not->toBeNull()
        ->and($promoCode->coupon_id)->toBe($coupon->id)
        ->and($promoCode->code)->toBe('SAVE15')
        ->and($promoCode->active)->toBeTrue()
        ->and($promoCode->first_time_transaction)->toBeTrue()
        ->and($promoCode->minimum_amount)->toBe(5000)
        ->and($promoCode->minimum_amount_currency)->toBe('usd')
        ->and($promoCode->expires_at)->not->toBeNull();
})->group('stripe', 'billing');

// ──────────────────────────────────────────────────────────────
// Edge cases
// ──────────────────────────────────────────────────────────────

test('handler gracefully handles missing data object', function () {
    $event = new WebhookReceived([
        'type' => 'invoice.created',
        'data' => [],
    ]);

    $this->listener->handle($event);

    expect(Invoice::query()->count())->toBe(0);
})->group('stripe', 'billing');

test('handler gracefully handles unknown customer by logging error', function () {
    $stripeChargeId = 'ch_'.uniqid();

    $event = new WebhookReceived([
        'type' => 'charge.succeeded',
        'data' => [
            'object' => [
                'id' => $stripeChargeId,
                'customer' => 'cus_nonexistent',
                'amount' => 1000,
                'currency' => 'usd',
                'status' => 'succeeded',
                'payment_method_details' => ['type' => 'card', 'card' => ['brand' => 'visa', 'last4' => '4242']],
                'amount_refunded' => 0,
                'metadata' => [],
            ],
        ],
    ]);

    // With NOT NULL constraints on billable_type/billable_id, the insert will
    // fail for unknown customers. The handler catches the error and logs it.
    $this->listener->handle($event);

    // The transaction should not be created since billable fields are NOT NULL
    // and we can't resolve the customer
    $transaction = Transaction::query()->where('stripe_id', $stripeChargeId)->first();
    expect($transaction)->toBeNull();
})->group('stripe', 'billing');

test('charge.succeeded links to local invoice when present', function () {
    $stripeChargeId = 'ch_'.uniqid();
    $stripeInvoiceId = 'in_'.uniqid();

    $localInvoice = Invoice::query()->create([
        'stripe_id' => $stripeInvoiceId,
        'stripe_customer_id' => $this->user->stripe_id,
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'status' => 'paid',
        'currency' => 'usd',
        'amount_due' => 1000,
        'amount_paid' => 1000,
        'amount_remaining' => 0,
        'subtotal' => 1000,
        'total' => 1000,
    ]);

    $event = new WebhookReceived([
        'type' => 'charge.succeeded',
        'data' => [
            'object' => [
                'id' => $stripeChargeId,
                'customer' => $this->user->stripe_id,
                'amount' => 1000,
                'currency' => 'usd',
                'status' => 'succeeded',
                'invoice' => $stripeInvoiceId,
                'payment_method_details' => ['type' => 'card', 'card' => ['brand' => 'visa', 'last4' => '4242']],
                'amount_refunded' => 0,
                'metadata' => [],
            ],
        ],
    ]);

    $this->listener->handle($event);

    $transaction = Transaction::query()->where('stripe_id', $stripeChargeId)->first();

    expect($transaction->invoice_id)->toBe($localInvoice->id);
})->group('stripe', 'billing');

test('invoice.created extracts line items correctly', function () {
    $stripeInvoiceId = 'in_'.uniqid();

    $event = new WebhookReceived([
        'type' => 'invoice.created',
        'data' => [
            'object' => [
                'id' => $stripeInvoiceId,
                'customer' => $this->user->stripe_id,
                'subscription' => null,
                'number' => 'INV-003',
                'status' => 'draft',
                'currency' => 'usd',
                'amount_due' => 1000,
                'amount_paid' => 0,
                'amount_remaining' => 1000,
                'subtotal' => 1000,
                'tax' => 0,
                'total' => 1000,
                'period_start' => now()->timestamp,
                'period_end' => now()->addMonth()->timestamp,
                'lines' => [
                    'data' => [
                        [
                            'id' => 'il_123',
                            'description' => 'Premium Plan (Monthly)',
                            'amount' => 1000,
                            'currency' => 'usd',
                            'quantity' => 1,
                            'price' => [
                                'id' => 'price_premium_monthly',
                                'product' => 'prod_premium',
                            ],
                        ],
                    ],
                ],
                'metadata' => [],
            ],
        ],
    ]);

    $this->listener->handle($event);

    $invoice = Invoice::query()->where('stripe_id', $stripeInvoiceId)->first();

    expect($invoice->line_items)->toBeArray()
        ->and($invoice->line_items)->toHaveCount(1)
        ->and($invoice->line_items[0]['stripe_id'])->toBe('il_123')
        ->and($invoice->line_items[0]['description'])->toBe('Premium Plan (Monthly)')
        ->and($invoice->line_items[0]['price_id'])->toBe('price_premium_monthly');
})->group('stripe', 'billing');

test('duplicate coupon redemption for same invoice is prevented', function () {
    $coupon = Coupon::query()->create([
        'stripe_id' => 'coupon_dedup_'.uniqid(),
        'name' => 'Dedup Test',
        'percent_off' => 10,
        'duration' => 'once',
        'active' => true,
        'valid' => true,
    ]);

    $subscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'plan_id' => $this->plan->id,
        'stripe_id' => 'sub_dedup_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => $this->plan->monthly_price_id,
        'cycle' => 'month',
        'quantity' => 1,
    ]);

    $stripeInvoiceId = 'in_dedup_'.uniqid();

    $payload = [
        'type' => 'invoice.paid',
        'data' => [
            'object' => [
                'id' => $stripeInvoiceId,
                'customer' => $this->user->stripe_id,
                'subscription' => $subscription->stripe_id,
                'number' => 'INV-DEDUP',
                'status' => 'paid',
                'currency' => 'usd',
                'amount_due' => 900,
                'amount_paid' => 900,
                'amount_remaining' => 0,
                'subtotal' => 1000,
                'tax' => 0,
                'total' => 900,
                'period_start' => now()->timestamp,
                'period_end' => now()->addMonth()->timestamp,
                'status_transitions' => ['paid_at' => now()->timestamp],
                'discount' => [
                    'coupon' => ['id' => $coupon->stripe_id],
                ],
                'lines' => ['data' => []],
                'metadata' => [],
            ],
        ],
    ];

    $this->listener->handle(new WebhookReceived($payload));
    $this->listener->handle(new WebhookReceived($payload));

    expect(CouponRedemption::query()->count())->toBe(1);
})->group('stripe', 'billing');
