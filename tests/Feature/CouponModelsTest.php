<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Wave\Coupon;
use Wave\CouponRedemption;
use Wave\PromotionCode;

beforeEach(function () {
    // Ensure all required tables exist, handling FK dependencies by disabling checks
    DB::statement('SET FOREIGN_KEY_CHECKS = 0');

    if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'deleted_at')) {
        Schema::dropIfExists('users');
        DB::statement('CREATE TABLE users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            username VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            email_verified_at TIMESTAMP NULL,
            password VARCHAR(255) NOT NULL,
            avatar VARCHAR(255) NULL,
            remember_token VARCHAR(100) NULL,
            deleted_at TIMESTAMP NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        )');
    } else {
        DB::table('users')->truncate();
    }

    if (! Schema::hasTable('subscriptions')) {
        DB::statement('CREATE TABLE subscriptions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            type VARCHAR(255) NOT NULL,
            stripe_id VARCHAR(255) NOT NULL UNIQUE,
            stripe_status VARCHAR(255) NOT NULL,
            stripe_price VARCHAR(255) NULL,
            quantity INT NULL,
            trial_ends_at TIMESTAMP NULL,
            ends_at TIMESTAMP NULL,
            billable_type VARCHAR(255) NULL,
            billable_id BIGINT UNSIGNED NULL,
            plan_id BIGINT UNSIGNED NULL,
            cycle VARCHAR(255) NULL,
            last_payment_at TIMESTAMP NULL,
            next_payment_at TIMESTAMP NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        )');
    }

    if (! Schema::hasTable('invoices')) {
        DB::statement('CREATE TABLE invoices (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            stripe_id VARCHAR(255) NULL,
            billable_type VARCHAR(255) NULL,
            billable_id BIGINT UNSIGNED NULL,
            subscription_id BIGINT UNSIGNED NULL,
            number VARCHAR(255) NULL,
            status VARCHAR(255) NULL,
            currency VARCHAR(255) NULL,
            amount_due INT NULL,
            amount_paid INT NULL,
            amount_remaining INT NULL,
            subtotal INT NULL,
            tax INT NULL,
            total INT NULL,
            period_start TIMESTAMP NULL,
            period_end TIMESTAMP NULL,
            due_date TIMESTAMP NULL,
            paid_at TIMESTAMP NULL,
            hosted_invoice_url TEXT NULL,
            invoice_pdf TEXT NULL,
            line_items JSON NULL,
            metadata JSON NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        )');
    }

    if (! Schema::hasTable('coupons')) {
        DB::statement('CREATE TABLE coupons (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            stripe_id VARCHAR(255) NOT NULL UNIQUE,
            name VARCHAR(255) NULL,
            amount_off INT NULL,
            percent_off DECIMAL(5,2) NULL,
            currency VARCHAR(255) NULL,
            duration VARCHAR(255) NOT NULL,
            duration_in_months INT NULL,
            max_redemptions INT NULL,
            times_redeemed INT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            valid TINYINT(1) NOT NULL DEFAULT 1,
            redeem_by TIMESTAMP NULL,
            metadata JSON NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        )');
    } else {
        DB::table('coupon_redemptions')->truncate();
        DB::table('promotion_codes')->truncate();
        DB::table('coupons')->truncate();
    }

    if (! Schema::hasTable('promotion_codes')) {
        DB::statement('CREATE TABLE promotion_codes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            stripe_id VARCHAR(255) NOT NULL UNIQUE,
            coupon_id BIGINT UNSIGNED NOT NULL,
            code VARCHAR(255) NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            max_redemptions INT NULL,
            times_redeemed INT NOT NULL DEFAULT 0,
            first_time_transaction TINYINT(1) NOT NULL DEFAULT 0,
            minimum_amount INT NULL,
            minimum_amount_currency VARCHAR(255) NULL,
            expires_at TIMESTAMP NULL,
            metadata JSON NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE
        )');
    }

    if (! Schema::hasTable('coupon_redemptions')) {
        DB::statement('CREATE TABLE coupon_redemptions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            coupon_id BIGINT UNSIGNED NOT NULL,
            promotion_code_id BIGINT UNSIGNED NULL,
            invoice_id BIGINT UNSIGNED NULL,
            subscription_id BIGINT UNSIGNED NULL,
            billable_type VARCHAR(255) NOT NULL,
            billable_id BIGINT UNSIGNED NOT NULL,
            discount_amount INT NOT NULL,
            redeemed_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
            FOREIGN KEY (promotion_code_id) REFERENCES promotion_codes(id) ON DELETE SET NULL,
            FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
            FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL
        )');
    }

    DB::statement('SET FOREIGN_KEY_CHECKS = 1');
});

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
