<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = $this->faker->randomElement(['draft', 'open', 'paid', 'void', 'uncollectible']);
        $subtotal = $this->faker->numberBetween(500, 50000);
        $tax = $this->faker->optional(0.7)->numberBetween(100, 5000);
        $total = $subtotal + ($tax ?? 0);
        $amountPaid = $status === 'paid' ? $total : 0;
        $amountRemaining = $total - $amountPaid;

        return [
            'stripe_id' => 'in_'.$this->faker->regexify('[A-Za-z0-9]{24}'),
            'stripe_customer_id' => 'cus_'.$this->faker->regexify('[A-Za-z0-9]{14}'),
            'billable_type' => 'user',
            'billable_id' => User::factory(),
            'subscription_id' => null,
            'number' => strtoupper($this->faker->regexify('[A-Z0-9]{8}')).'-'.$this->faker->numberBetween(1, 9999),
            'status' => $status,
            'currency' => 'usd',
            'amount_due' => $total,
            'amount_paid' => $amountPaid,
            'amount_remaining' => $amountRemaining,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
            'period_start' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'period_end' => $this->faker->dateTimeBetween('now', '+1 month'),
            'due_date' => $this->faker->optional()->dateTimeBetween('now', '+30 days'),
            'paid_at' => $status === 'paid' ? $this->faker->dateTimeBetween('-1 month', 'now') : null,
            'hosted_invoice_url' => $this->faker->optional()->url(),
            'invoice_pdf' => $this->faker->optional()->url(),
            'line_items' => null,
            'metadata' => null,
        ];
    }

    /**
     * Indicate that the invoice is paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'amount_paid' => $attributes['total'],
            'amount_remaining' => 0,
            'paid_at' => now(),
        ]);
    }

    /**
     * Indicate that the invoice is open/unpaid.
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'open',
            'amount_paid' => 0,
            'amount_remaining' => $attributes['total'],
            'paid_at' => null,
        ]);
    }

    /**
     * Indicate that the invoice is void.
     */
    public function void(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'void',
            'amount_paid' => 0,
            'amount_remaining' => 0,
            'paid_at' => null,
        ]);
    }
}
