<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Wave\Coupon;
use Wave\PromotionCode;

class StripeSyncCoupons extends Command
{
    protected $signature = 'stripe:sync-coupons';

    protected $description = 'Backfill coupons and promotion codes from Stripe into the local database';

    protected int $couponsCreated = 0;

    protected int $couponsUpdated = 0;

    protected int $promoCodesCreated = 0;

    protected int $promoCodesUpdated = 0;

    public function handle(): int
    {
        $stripe = $this->makeStripeClient();

        try {
            $this->syncCoupons($stripe);
            $this->syncPromotionCodes($stripe);
        } catch (ApiErrorException $e) {
            $this->error('Stripe API error: '.$e->getMessage());

            return self::FAILURE;
        }

        $totalCoupons = $this->couponsCreated + $this->couponsUpdated;
        $totalPromoCodes = $this->promoCodesCreated + $this->promoCodesUpdated;

        $this->info("Synced {$totalCoupons} coupons ({$this->couponsCreated} created, {$this->couponsUpdated} updated).");
        $this->info("Synced {$totalPromoCodes} promotion codes ({$this->promoCodesCreated} created, {$this->promoCodesUpdated} updated).");

        return self::SUCCESS;
    }

    /**
     * Sync all coupons from Stripe.
     */
    protected function syncCoupons(StripeClient $stripe): void
    {
        $this->info('Syncing coupons from Stripe...');

        $coupons = $stripe->coupons->all(['limit' => 100]);
        $items = iterator_to_array($coupons->autoPagingIterator());

        if (empty($items)) {
            $this->warn('No coupons found in Stripe.');

            return;
        }

        $bar = $this->output->createProgressBar(count($items));
        $bar->start();

        foreach ($items as $stripeCoupon) {
            $this->upsertCoupon($stripeCoupon);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Create or update a local coupon from a Stripe coupon object.
     *
     * @param  \Stripe\Coupon  $stripeCoupon
     */
    protected function upsertCoupon(mixed $stripeCoupon): void
    {
        $attributes = [
            'name' => $stripeCoupon->name,
            'amount_off' => $stripeCoupon->amount_off,
            'percent_off' => $stripeCoupon->percent_off,
            'currency' => $stripeCoupon->currency,
            'duration' => $stripeCoupon->duration ?? 'once',
            'duration_in_months' => $stripeCoupon->duration_in_months,
            'max_redemptions' => $stripeCoupon->max_redemptions,
            'times_redeemed' => $stripeCoupon->times_redeemed ?? 0,
            'active' => $stripeCoupon->valid ?? true,
            'valid' => $stripeCoupon->valid ?? true,
            'redeem_by' => $stripeCoupon->redeem_by ? Carbon::createFromTimestamp($stripeCoupon->redeem_by) : null,
            'metadata' => $stripeCoupon->metadata ? (array) $stripeCoupon->metadata : null,
        ];

        $existing = Coupon::query()->where('stripe_id', $stripeCoupon->id)->first();

        if ($existing) {
            $existing->update($attributes);
            $this->couponsUpdated++;
        } else {
            Coupon::create(array_merge(['stripe_id' => $stripeCoupon->id], $attributes));
            $this->couponsCreated++;
        }
    }

    /**
     * Sync all promotion codes from Stripe.
     */
    protected function syncPromotionCodes(StripeClient $stripe): void
    {
        $this->info('Syncing promotion codes from Stripe...');

        $promoCodes = $stripe->promotionCodes->all(['limit' => 100]);
        $items = iterator_to_array($promoCodes->autoPagingIterator());

        if (empty($items)) {
            $this->warn('No promotion codes found in Stripe.');

            return;
        }

        $bar = $this->output->createProgressBar(count($items));
        $bar->start();

        foreach ($items as $stripePromoCode) {
            $this->upsertPromotionCode($stripePromoCode);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Create or update a local promotion code from a Stripe promotion code object.
     *
     * @param  \Stripe\PromotionCode  $stripePromoCode
     */
    protected function upsertPromotionCode(mixed $stripePromoCode): void
    {
        $couponStripeId = $stripePromoCode->coupon->id ?? ($stripePromoCode->coupon ?? null);
        $localCoupon = $couponStripeId
            ? Coupon::query()->where('stripe_id', $couponStripeId)->first()
            : null;

        if (! $localCoupon) {
            $this->warn("Skipping promotion code {$stripePromoCode->id}: no local coupon found for Stripe coupon {$couponStripeId}.");

            return;
        }

        $restrictions = $stripePromoCode->restrictions ?? null;

        $attributes = [
            'coupon_id' => $localCoupon->id,
            'code' => $stripePromoCode->code,
            'active' => $stripePromoCode->active ?? true,
            'max_redemptions' => $stripePromoCode->max_redemptions,
            'times_redeemed' => $stripePromoCode->times_redeemed ?? 0,
            'first_time_transaction' => $restrictions->first_time_transaction ?? false,
            'minimum_amount' => $restrictions->minimum_amount ?? null,
            'minimum_amount_currency' => $restrictions->minimum_amount_currency ?? null,
            'expires_at' => $stripePromoCode->expires_at ? Carbon::createFromTimestamp($stripePromoCode->expires_at) : null,
            'metadata' => $stripePromoCode->metadata ? (array) $stripePromoCode->metadata : null,
        ];

        $existing = PromotionCode::query()->where('stripe_id', $stripePromoCode->id)->first();

        if ($existing) {
            $existing->update($attributes);
            $this->promoCodesUpdated++;
        } else {
            PromotionCode::create(array_merge(['stripe_id' => $stripePromoCode->id], $attributes));
            $this->promoCodesCreated++;
        }
    }

    /**
     * Resolve the Stripe client instance from the container or create a new one.
     */
    protected function makeStripeClient(): StripeClient
    {
        if (app()->bound(StripeClient::class)) {
            return app(StripeClient::class);
        }

        return new StripeClient(config('services.stripe.secret'));
    }
}
