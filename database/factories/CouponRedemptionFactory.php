<?php

namespace Database\Factories;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CouponRedemption>
 */
class CouponRedemptionFactory extends Factory
{
    protected $model = CouponRedemption::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'coupon_id' => Coupon::factory(),
            'promotion_code_id' => null,
            'invoice_id' => null,
            'subscription_id' => null,
            'billable_type' => (new User())->getMorphClass(),
            'billable_id' => User::factory(),
            'discount_amount' => $this->faker->randomElement([500, 1000, 1500, 2000, 2500, 5000]),
            'redeemed_at' => $this->faker->dateTimeBetween('-3 months', 'now'),
        ];
    }

    /**
     * Associate a specific coupon with this redemption.
     */
    public function forCoupon(Coupon $coupon): static
    {
        return $this->state(fn (array $attributes) => [
            'coupon_id' => $coupon->id,
        ]);
    }

    /**
     * Associate a promotion code with this redemption.
     */
    public function withPromotionCode(int $promotionCodeId): static
    {
        return $this->state(fn (array $attributes) => [
            'promotion_code_id' => $promotionCodeId,
        ]);
    }
}
