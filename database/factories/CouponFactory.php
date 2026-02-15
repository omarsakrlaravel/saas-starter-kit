<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Wave\Coupon;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $isPercent = $this->faker->boolean(60);

        return [
            'stripe_id' => 'coupon_'.$this->faker->regexify('[A-Za-z0-9]{14}'),
            'name' => $this->faker->words(3, true).' discount',
            'amount_off' => $isPercent ? null : $this->faker->randomElement([500, 1000, 1500, 2000, 2500, 5000]),
            'percent_off' => $isPercent ? $this->faker->randomElement([5.00, 10.00, 15.00, 20.00, 25.00, 50.00]) : null,
            'currency' => $isPercent ? null : 'usd',
            'duration' => $this->faker->randomElement(['forever', 'once', 'repeating']),
            'duration_in_months' => null,
            'max_redemptions' => $this->faker->optional(0.3)->numberBetween(10, 1000),
            'times_redeemed' => $this->faker->numberBetween(0, 50),
            'active' => true,
            'valid' => true,
            'redeem_by' => $this->faker->optional(0.3)->dateTimeBetween('+1 month', '+1 year'),
            'metadata' => null,
        ];
    }

    /**
     * Indicate the coupon is a percentage discount.
     */
    public function percentage(float $percent = 20.00): static
    {
        return $this->state(fn (array $attributes) => [
            'amount_off' => null,
            'percent_off' => $percent,
            'currency' => null,
        ]);
    }

    /**
     * Indicate the coupon is a fixed amount discount.
     */
    public function fixedAmount(int $amountInCents = 1000): static
    {
        return $this->state(fn (array $attributes) => [
            'amount_off' => $amountInCents,
            'percent_off' => null,
            'currency' => 'usd',
        ]);
    }

    /**
     * Indicate the coupon has a repeating duration.
     */
    public function repeating(int $months = 3): static
    {
        return $this->state(fn (array $attributes) => [
            'duration' => 'repeating',
            'duration_in_months' => $months,
        ]);
    }

    /**
     * Indicate the coupon is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    /**
     * Indicate the coupon is no longer valid.
     */
    public function invalid(): static
    {
        return $this->state(fn (array $attributes) => [
            'valid' => false,
        ]);
    }

    /**
     * Indicate the coupon has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'redeem_by' => $this->faker->dateTimeBetween('-6 months', '-1 day'),
            'valid' => false,
        ]);
    }
}
