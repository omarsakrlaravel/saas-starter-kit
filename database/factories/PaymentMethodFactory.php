<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Wave\PaymentMethod;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $brand = $this->faker->randomElement(['visa', 'mastercard', 'amex', 'discover']);

        return [
            'stripe_id' => 'pm_'.$this->faker->regexify('[A-Za-z0-9]{24}'),
            'stripe_customer_id' => 'cus_'.$this->faker->regexify('[A-Za-z0-9]{14}'),
            'billable_type' => 'user',
            'billable_id' => User::factory(),
            'type' => 'card',
            'brand' => $brand,
            'last4' => (string) $this->faker->numberBetween(1000, 9999),
            'exp_month' => $this->faker->numberBetween(1, 12),
            'exp_year' => $this->faker->numberBetween(2026, 2032),
            'is_default' => false,
            'metadata' => null,
        ];
    }

    /**
     * Indicate that this is the default payment method.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    /**
     * Indicate that this is a SEPA debit payment method.
     */
    public function sepaDebit(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'sepa_debit',
            'brand' => null,
            'exp_month' => null,
            'exp_year' => null,
        ]);
    }

    /**
     * Indicate that this is a bank transfer payment method.
     */
    public function bankTransfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'bank_transfer',
            'brand' => null,
            'exp_month' => null,
            'exp_year' => null,
        ]);
    }
}
