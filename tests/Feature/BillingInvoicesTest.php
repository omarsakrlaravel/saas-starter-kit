<?php

use App\Models\User;
use Carbon\Carbon;

test('billing invoices returns empty array when user does not have stripe id', function () {
    $user = new User();

    expect($user->billingInvoices())->toBe([]);
});

test('billing invoices maps cashier invoice download urls', function () {
    $cashierInvoice = new class()
    {
        public string $id = 'in_test_123';

        public function date(): Carbon
        {
            return Carbon::create(2026, 2, 14, 15, 30, 0, 'UTC');
        }

        public function asStripeInvoice(): object
        {
            return (object) [
                'number' => 'INV-2026-0001',
                'currency' => 'usd',
                'status' => 'paid',
                'billing_reason' => 'subscription_update',
                'subtotal' => 1000,
                'tax' => 200,
                'total' => 1200,
                'total_discount_amounts' => [
                    (object) ['amount' => 100],
                ],
                'lines' => (object) [
                    'data' => [
                        (object) [
                            'description' => 'Seat increase proration',
                            'amount' => 900,
                            'parent' => (object) [
                                'subscription_item_details' => (object) ['proration' => true],
                            ],
                        ],
                        (object) [
                            'description' => 'Unused time credit',
                            'amount' => -100,
                            'parent' => (object) [
                                'invoice_item_details' => (object) ['proration' => true],
                            ],
                        ],
                    ],
                ],
                'invoice_pdf' => 'https://stripe.test/invoices/in_test_123.pdf',
            ];
        }
    };

    $user = \Mockery::mock(User::class)->makePartial();
    $user->stripe_id = 'cus_test_123';
    $user->shouldReceive('invoicesIncludingPending')->once()->andReturn(collect([$cashierInvoice]));
    $user->shouldNotReceive('invoices');

    $invoices = $user->billingInvoices();

    expect($invoices)->toHaveCount(1)
        ->and($invoices[0]->id)->toBe('in_test_123')
        ->and($invoices[0]->number)->toBe('INV-2026-0001')
        ->and($invoices[0]->status)->toBe('Paid')
        ->and($invoices[0]->reason)->toBe('Prorated adjustment')
        ->and($invoices[0]->subtotal)->toBe('$10.00')
        ->and($invoices[0]->discount)->toBe('$1.00')
        ->and($invoices[0]->tax)->toBe('$2.00')
        ->and($invoices[0]->total)->toBe('$12.00')
        ->and($invoices[0]->calculation)->toBe('Subtotal $10.00 - Discount $1.00 + Tax $2.00 = Total $12.00')
        ->and($invoices[0]->line_items)->toHaveCount(2)
        ->and($invoices[0]->line_items[0]['description'])->toBe('Seat increase proration')
        ->and($invoices[0]->line_items[1]['raw_amount'])->toBe(-100)
        ->and($invoices[0]->download)->toBe('https://stripe.test/invoices/in_test_123.pdf');
});

test('billing invoices includes pending invoices with hosted invoice urls', function () {
    $cashierInvoice = new class()
    {
        public string $id = 'in_open_123';

        public function date(): Carbon
        {
            return Carbon::create(2026, 2, 14, 16, 45, 0, 'UTC');
        }

        public function asStripeInvoice(): object
        {
            return (object) [
                'number' => 'INV-2026-0002',
                'currency' => 'usd',
                'status' => 'open',
                'billing_reason' => 'subscription_cycle',
                'subtotal' => 2500,
                'tax' => 0,
                'total' => 2500,
                'total_discount_amounts' => [],
                'lines' => (object) [
                    'data' => [
                        (object) [
                            'description' => 'Monthly subscription',
                            'amount' => 2500,
                            'parent' => (object) [
                                'subscription_item_details' => (object) ['proration' => false],
                            ],
                        ],
                    ],
                ],
                'hosted_invoice_url' => 'https://stripe.test/invoices/in_open_123',
            ];
        }
    };

    $user = \Mockery::mock(User::class)->makePartial();
    $user->stripe_id = 'cus_test_123';
    $user->shouldReceive('invoicesIncludingPending')->once()->andReturn(collect([$cashierInvoice]));
    $user->shouldNotReceive('invoices');

    $invoices = $user->billingInvoices();

    expect($invoices)->toHaveCount(1)
        ->and($invoices[0]->id)->toBe('in_open_123')
        ->and($invoices[0]->status)->toBe('Open')
        ->and($invoices[0]->status_value)->toBe('open')
        ->and($invoices[0]->download)->toBe('https://stripe.test/invoices/in_open_123');
});
