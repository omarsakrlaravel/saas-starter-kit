<?php

/**
 * Billing Models Test Suite
 *
 * Tests the Invoice, Transaction, and PaymentMethod models including:
 * - Model creation via factories
 * - Relationship definitions (morphTo, belongsTo, hasMany)
 * - Attribute casting (dates, integers, arrays, booleans)
 * - Factory states (paid, open, void, succeeded, failed, refunded, default)
 */

use App\Models\User;
use Wave\Invoice;
use Wave\PaymentMethod;
use Wave\Subscription;
use Wave\Transaction;

beforeEach(function () {
    $this->user = User::factory()->create();
});

afterEach(function () {
    // Clean up test records
    Transaction::query()->delete();
    Invoice::query()->delete();
    PaymentMethod::query()->delete();
});

// --- Invoice Model Tests ---

test('invoice can be created via factory', function () {
    $invoice = Invoice::factory()->create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
    ]);

    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->exists)->toBeTrue()
        ->and($invoice->stripe_id)->toStartWith('in_')
        ->and($invoice->stripe_customer_id)->toStartWith('cus_');
});

test('invoice casts date fields correctly', function () {
    $invoice = Invoice::factory()->paid()->create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'period_start' => '2026-01-01 00:00:00',
        'period_end' => '2026-02-01 00:00:00',
        'due_date' => '2026-01-15 00:00:00',
        'paid_at' => '2026-01-10 00:00:00',
    ]);

    expect($invoice->period_start)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($invoice->period_end)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($invoice->due_date)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($invoice->paid_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

test('invoice casts integer fields correctly', function () {
    $invoice = Invoice::factory()->create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'amount_due' => 2500,
        'amount_paid' => 2500,
        'amount_remaining' => 0,
        'subtotal' => 2000,
        'tax' => 500,
        'total' => 2500,
    ]);

    expect($invoice->amount_due)->toBeInt()
        ->and($invoice->subtotal)->toBeInt()
        ->and($invoice->tax)->toBeInt()
        ->and($invoice->total)->toBeInt();
});

test('invoice casts json fields correctly', function () {
    $lineItems = [['description' => 'Pro plan', 'amount' => 2000]];
    $metadata = ['source' => 'checkout'];

    $invoice = Invoice::factory()->create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'line_items' => $lineItems,
        'metadata' => $metadata,
    ]);

    expect($invoice->line_items)->toBeArray()
        ->and($invoice->line_items[0]['description'])->toBe('Pro plan')
        ->and($invoice->metadata)->toBeArray()
        ->and($invoice->metadata['source'])->toBe('checkout');
});

test('invoice belongs to billable via morph', function () {
    $invoice = Invoice::factory()->create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
    ]);

    expect($invoice->billable)->toBeInstanceOf(User::class)
        ->and($invoice->billable->id)->toBe($this->user->id);
});

test('invoice belongs to subscription', function () {
    $subscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'stripe_id' => 'sub_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_pro_monthly',
        'cycle' => 'month',
        'quantity' => 1,
    ]);

    $invoice = Invoice::factory()->create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'subscription_id' => $subscription->id,
    ]);

    expect($invoice->subscription)->toBeInstanceOf(Subscription::class)
        ->and($invoice->subscription->id)->toBe($subscription->id);
});

test('invoice has many transactions', function () {
    $invoice = Invoice::factory()->create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
    ]);

    Transaction::factory()->count(3)->create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'invoice_id' => $invoice->id,
    ]);

    expect($invoice->transactions)->toHaveCount(3)
        ->and($invoice->transactions->first())->toBeInstanceOf(Transaction::class);
});

test('invoice factory paid state works', function () {
    $invoice = Invoice::factory()->paid()->create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
    ]);

    expect($invoice->status)->toBe('paid')
        ->and($invoice->amount_paid)->toBe($invoice->total)
        ->and($invoice->amount_remaining)->toBe(0)
        ->and($invoice->paid_at)->not->toBeNull();
});

test('invoice factory open state works', function () {
    $invoice = Invoice::factory()->open()->create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
    ]);

    expect($invoice->status)->toBe('open')
        ->and($invoice->amount_paid)->toBe(0)
        ->and($invoice->paid_at)->toBeNull();
});

test('invoice factory void state works', function () {
    $invoice = Invoice::factory()->void()->create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
    ]);

    expect($invoice->status)->toBe('void')
        ->and($invoice->amount_paid)->toBe(0)
        ->and($invoice->paid_at)->toBeNull();
});

// --- Transaction Model Tests ---

test('transaction can be created via factory', function () {
    $transaction = Transaction::factory()->create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
    ]);

    expect($transaction)->toBeInstanceOf(Transaction::class)
        ->and($transaction->exists)->toBeTrue()
        ->and($transaction->stripe_id)->toStartWith('ch_')
        ->and($transaction->stripe_customer_id)->toStartWith('cus_');
});

test('transaction casts integer fields correctly', function () {
    $transaction = Transaction::factory()->create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'amount' => 5000,
        'refunded_amount' => 1000,
    ]);

    expect($transaction->amount)->toBeInt()
        ->and($transaction->refunded_amount)->toBeInt();
});

test('transaction casts json metadata correctly', function () {
    $metadata = ['order_id' => 'ord_123'];

    $transaction = Transaction::factory()->create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'metadata' => $metadata,
    ]);

    expect($transaction->metadata)->toBeArray()
        ->and($transaction->metadata['order_id'])->toBe('ord_123');
});

test('transaction belongs to billable via morph', function () {
    $transaction = Transaction::factory()->create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
    ]);

    expect($transaction->billable)->toBeInstanceOf(User::class)
        ->and($transaction->billable->id)->toBe($this->user->id);
});

test('transaction belongs to invoice', function () {
    $invoice = Invoice::factory()->create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
    ]);

    $transaction = Transaction::factory()->create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'invoice_id' => $invoice->id,
    ]);

    expect($transaction->invoice)->toBeInstanceOf(Invoice::class)
        ->and($transaction->invoice->id)->toBe($invoice->id);
});

test('transaction factory succeeded state works', function () {
    $transaction = Transaction::factory()->succeeded()->create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
    ]);

    expect($transaction->status)->toBe('succeeded')
        ->and($transaction->failure_code)->toBeNull()
        ->and($transaction->failure_message)->toBeNull()
        ->and($transaction->refunded_amount)->toBe(0);
});

test('transaction factory failed state works', function () {
    $transaction = Transaction::factory()->failed()->create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
    ]);

    expect($transaction->status)->toBe('failed')
        ->and($transaction->failure_code)->toBe('card_declined')
        ->and($transaction->failure_message)->not->toBeNull();
});

test('transaction factory refunded state works', function () {
    $transaction = Transaction::factory()->refunded()->create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
    ]);

    expect($transaction->status)->toBe('refunded')
        ->and($transaction->refunded_amount)->toBe($transaction->amount);
});

// --- PaymentMethod Model Tests ---

test('payment method can be created via factory', function () {
    $paymentMethod = PaymentMethod::factory()->create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
    ]);

    expect($paymentMethod)->toBeInstanceOf(PaymentMethod::class)
        ->and($paymentMethod->exists)->toBeTrue()
        ->and($paymentMethod->stripe_id)->toStartWith('pm_')
        ->and($paymentMethod->stripe_customer_id)->toStartWith('cus_');
});

test('payment method casts fields correctly', function () {
    $paymentMethod = PaymentMethod::factory()->create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'exp_month' => 12,
        'exp_year' => 2028,
        'is_default' => true,
        'metadata' => ['billing_address' => '123 Main St'],
    ]);

    expect($paymentMethod->exp_month)->toBeInt()
        ->and($paymentMethod->exp_year)->toBeInt()
        ->and($paymentMethod->is_default)->toBeBool()->toBeTrue()
        ->and($paymentMethod->metadata)->toBeArray();
});

test('payment method belongs to billable via morph', function () {
    $paymentMethod = PaymentMethod::factory()->create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
    ]);

    expect($paymentMethod->billable)->toBeInstanceOf(User::class)
        ->and($paymentMethod->billable->id)->toBe($this->user->id);
});

test('payment method factory default state works', function () {
    $paymentMethod = PaymentMethod::factory()->default()->create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
    ]);

    expect($paymentMethod->is_default)->toBeTrue();
});

test('payment method factory sepa debit state works', function () {
    $paymentMethod = PaymentMethod::factory()->sepaDebit()->create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
    ]);

    expect($paymentMethod->type)->toBe('sepa_debit')
        ->and($paymentMethod->brand)->toBeNull()
        ->and($paymentMethod->exp_month)->toBeNull()
        ->and($paymentMethod->exp_year)->toBeNull();
});

test('payment method factory bank transfer state works', function () {
    $paymentMethod = PaymentMethod::factory()->bankTransfer()->create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
    ]);

    expect($paymentMethod->type)->toBe('bank_transfer')
        ->and($paymentMethod->brand)->toBeNull();
});
