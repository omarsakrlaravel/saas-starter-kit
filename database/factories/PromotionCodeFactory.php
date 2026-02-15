<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Wave\Coupon;
use Wave\PromotionCode;

/**
 * @extends Factory<PromotionCode>
 */
class PromotionCodeFactory extends Factory
{
    protected $model = PromotionCode::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stripe_id' => 'promo_'.$this->faker->regexify('[A-Za-z0-9]{14}'),
            'coupon_id' => Coupon::factory(),
            'code' => strtoupper($this->faker->bothify('???###')),
            'active' => true,
            'max_redemptions' => $this->faker->optional(0.3)->numberBetween(10, 500),
            'times_redeemed' => $this->faker->numberBetween(0, 20),
            'first_time_transaction' => $this->faker->boolean(20),
            'minimum_amount' => $this->faker->optional(0.2)->randomElement([1000, 2500, 5000, 10000]),
            'minimum_amount_currency' => null,
            'expires_at' => $this->faker->optional(0.3)->dateTimeBetween('+1 month', '+1 year'),
            'metadata' => null,
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (PromotionCode $promotionCode) {
            if ($promotionCode->minimum_amount !== null && $promotionCode->minimum_amount_currency === null) {
                $promotionCode->minimum_amount_currency = 'usd';
            }
        });
    }

    /**
     * Indicate the promotion code is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    /**
     * Indicate the promotion code has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => $this->faker->dateTimeBetween('-6 months', '-1 day'),
        ]);
    }

    /**
     * Indicate the promotion code is restricted to first-time transactions.
     */
    public function firstTimeOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'first_time_transaction' => true,
        ]);
    }

    /**
     * Indicate the promotion code has a minimum purchase amount.
     */
    public function withMinimumAmount(int $amountInCents = 5000, string $currency = 'usd'): static
    {
        return $this->state(fn (array $attributes) => [
            'minimum_amount' => $amountInCents,
            'minimum_amount_currency' => $currency,
        ]);
    }
}
