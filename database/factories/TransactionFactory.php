<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = $this->faker->randomElement(['succeeded', 'failed', 'pending', 'refunded', 'partially_refunded']);
        $amount = $this->faker->numberBetween(500, 50000);
        $brand = $this->faker->randomElement(['visa', 'mastercard', 'amex', 'discover']);

        return [
            'stripe_id' => 'ch_'.$this->faker->regexify('[A-Za-z0-9]{24}'),
            'stripe_customer_id' => 'cus_'.$this->faker->regexify('[A-Za-z0-9]{14}'),
            'invoice_id' => null,
            'billable_type' => 'user',
            'billable_id' => User::factory(),
            'amount' => $amount,
            'currency' => 'usd',
            'status' => $status,
            'payment_method_type' => 'card',
            'payment_method_last4' => (string) $this->faker->numberBetween(1000, 9999),
            'payment_method_brand' => $brand,
            'failure_code' => $status === 'failed' ? $this->faker->randomElement(['card_declined', 'insufficient_funds', 'expired_card']) : null,
            'failure_message' => $status === 'failed' ? $this->faker->sentence() : null,
            'refunded_amount' => $status === 'refunded' ? $amount : ($status === 'partially_refunded' ? (int) ($amount * 0.5) : 0),
            'description' => $this->faker->optional()->sentence(),
            'metadata' => null,
        ];
    }

    /**
     * Indicate that the transaction succeeded.
     */
    public function succeeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'succeeded',
            'failure_code' => null,
            'failure_message' => null,
            'refunded_amount' => 0,
        ]);
    }

    /**
     * Indicate that the transaction failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'failure_code' => 'card_declined',
            'failure_message' => 'Your card was declined.',
            'refunded_amount' => 0,
        ]);
    }

    /**
     * Indicate that the transaction was refunded.
     */
    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'refunded',
            'failure_code' => null,
            'failure_message' => null,
            'refunded_amount' => $attributes['amount'],
        ]);
    }
}
