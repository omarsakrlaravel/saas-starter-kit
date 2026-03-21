<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Wave\Coupon;
use Wave\CouponRedemption;
use Wave\PromotionCode;

uses(RefreshDatabase::class);

it('can create a coupon with factory', function () {
    $coupon = Coupon::factory()->create();

    expect($coupon)->toBeInstanceOf(Coupon::class)
        ->and($coupon->stripe_id)->toStartWith('coupon_')
        ->and($coupon->duration)->toBeIn(['forever', 'once', 'repeating'])
        ->and($coupon->active)->toBeTrue()
        ->and($coupon->valid)->toBeTrue();
});

it('can create a percentage coupon', function () {
    $coupon = Coupon::factory()->percentage(25.00)->create();

    expect($coupon->percent_off)->toBe('25.00')
        ->and($coupon->amount_off)->toBeNull()
        ->and($coupon->currency)->toBeNull();
});

it('can create a fixed amount coupon', function () {
    $coupon = Coupon::factory()->fixedAmount(1500)->create();

    expect($coupon->amount_off)->toBe(1500)
        ->and($coupon->percent_off)->toBeNull()
        ->and($coupon->currency)->toBe('usd');
});

it('displays discount as percentage', function () {
    $coupon = Coupon::factory()->percentage(20.00)->create();

    expect($coupon->discountDisplay())->toBe('20%');
});

it('displays discount as fixed amount', function () {
    $coupon = Coupon::factory()->fixedAmount(1000)->create();

    expect($coupon->discountDisplay())->toBe('$10.00 off');
});

it('can create a repeating coupon', function () {
    $coupon = Coupon::factory()->repeating(6)->create();

    expect($coupon->duration)->toBe('repeating')
        ->and($coupon->duration_in_months)->toBe(6);
});

it('can create an inactive coupon', function () {
    $coupon = Coupon::factory()->inactive()->create();

    expect($coupon->active)->toBeFalse();
});

it('can create an expired coupon', function () {
    $coupon = Coupon::factory()->expired()->create();

    expect($coupon->valid)->toBeFalse()
        ->and($coupon->redeem_by->isPast())->toBeTrue();
});

it('coupon has many promotion codes', function () {
    $coupon = Coupon::factory()->create();
    PromotionCode::factory()->count(3)->create(['coupon_id' => $coupon->id]);

    expect($coupon->promotionCodes)->toHaveCount(3);
});

it('coupon has many redemptions', function () {
    $coupon = Coupon::factory()->create();
    CouponRedemption::factory()->count(2)->create(['coupon_id' => $coupon->id]);

    expect($coupon->redemptions)->toHaveCount(2);
});

it('can create a promotion code with factory', function () {
    $promotionCode = PromotionCode::factory()->create();

    expect($promotionCode)->toBeInstanceOf(PromotionCode::class)
        ->and($promotionCode->stripe_id)->toStartWith('promo_')
        ->and($promotionCode->active)->toBeTrue()
        ->and($promotionCode->coupon)->toBeInstanceOf(Coupon::class);
});

it('promotion code belongs to a coupon', function () {
    $coupon = Coupon::factory()->create();
    $promoCode = PromotionCode::factory()->create(['coupon_id' => $coupon->id]);

    expect($promoCode->coupon->id)->toBe($coupon->id);
});

it('can create an expired promotion code', function () {
    $promoCode = PromotionCode::factory()->expired()->create();

    expect($promoCode->expires_at->isPast())->toBeTrue();
});

it('can create a first-time-only promotion code', function () {
    $promoCode = PromotionCode::factory()->firstTimeOnly()->create();

    expect($promoCode->first_time_transaction)->toBeTrue();
});

it('can create a promotion code with minimum amount', function () {
    $promoCode = PromotionCode::factory()->withMinimumAmount(5000, 'usd')->create();

    expect($promoCode->minimum_amount)->toBe(5000)
        ->and($promoCode->minimum_amount_currency)->toBe('usd');
});

it('can create a coupon redemption with factory', function () {
    $redemption = CouponRedemption::factory()->create();

    expect($redemption)->toBeInstanceOf(CouponRedemption::class)
        ->and($redemption->discount_amount)->toBeGreaterThan(0)
        ->and($redemption->coupon)->toBeInstanceOf(Coupon::class);
});

it('coupon redemption belongs to a coupon', function () {
    $coupon = Coupon::factory()->create();
    $redemption = CouponRedemption::factory()->forCoupon($coupon)->create();

    expect($redemption->coupon->id)->toBe($coupon->id);
});

it('coupon redemption has billable morph relationship', function () {
    $redemption = CouponRedemption::factory()->create();

    expect($redemption->billable)->not->toBeNull();
});

it('cascade deletes promotion codes when coupon is deleted', function () {
    $coupon = Coupon::factory()->create();
    PromotionCode::factory()->count(3)->create(['coupon_id' => $coupon->id]);

    expect(PromotionCode::where('coupon_id', $coupon->id)->count())->toBe(3);

    $coupon->delete();

    expect(PromotionCode::where('coupon_id', $coupon->id)->count())->toBe(0);
});

it('cascade deletes redemptions when coupon is deleted', function () {
    $coupon = Coupon::factory()->create();
    CouponRedemption::factory()->count(2)->create(['coupon_id' => $coupon->id]);

    expect(CouponRedemption::where('coupon_id', $coupon->id)->count())->toBe(2);

    $coupon->delete();

    expect(CouponRedemption::where('coupon_id', $coupon->id)->count())->toBe(0);
});

it('nullifies promotion code on redemption when promotion code is deleted', function () {
    $promoCode = PromotionCode::factory()->create();
    $redemption = CouponRedemption::factory()->create([
        'coupon_id' => $promoCode->coupon_id,
        'promotion_code_id' => $promoCode->id,
    ]);

    $promoCode->delete();

    expect($redemption->fresh()->promotion_code_id)->toBeNull();
});
