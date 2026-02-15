<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Wave\PaymentMethod;

class StripeSyncPaymentMethods extends Command
{
    protected $signature = 'stripe:sync-payment-methods {--customer= : Sync only for specific Stripe customer ID}';

    protected $description = 'Backfill payment methods from Stripe into the local database';

    protected int $created = 0;

    protected int $updated = 0;

    protected int $removed = 0;

    public function handle(): int
    {
        $stripe = $this->makeStripeClient();

        $customerFilter = $this->option('customer');

        try {
            if ($customerFilter) {
                $this->syncCustomerPaymentMethods($stripe, $customerFilter);
            } else {
                $this->syncAllCustomerPaymentMethods($stripe);
            }
        } catch (ApiErrorException $e) {
            $this->error('Stripe API error: '.$e->getMessage());

            return self::FAILURE;
        }

        $total = $this->created + $this->updated;
        $this->info("Synced {$total} payment methods ({$this->created} created, {$this->updated} updated, {$this->removed} removed).");

        return self::SUCCESS;
    }

    /**
     * Sync payment methods for all users that have a Stripe customer ID.
     */
    protected function syncAllCustomerPaymentMethods(StripeClient $stripe): void
    {
        $users = User::query()
            ->whereNotNull('stripe_id')
            ->where('stripe_id', '!=', '')
            ->get(['id', 'stripe_id']);

        if ($users->isEmpty()) {
            $this->warn('No users with a Stripe customer ID found.');

            return;
        }

        $this->info("Found {$users->count()} users with Stripe IDs. Syncing payment methods...");

        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        foreach ($users as $user) {
            try {
                $this->syncCustomerPaymentMethods($stripe, $user->stripe_id);
            } catch (ApiErrorException $e) {
                $this->newLine();
                $this->warn("Failed to sync payment methods for customer {$user->stripe_id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Sync all payment methods for a single Stripe customer.
     */
    protected function syncCustomerPaymentMethods(StripeClient $stripe, string $customerId): void
    {
        $billable = $this->resolveBillable($customerId);

        if (! $billable) {
            return;
        }

        // Fetch the customer to determine their default payment method
        $customer = $stripe->customers->retrieve($customerId);
        $defaultPaymentMethodId = $customer->invoice_settings->default_payment_method ?? null;

        // Fetch all payment methods for this customer
        $paymentMethods = $stripe->paymentMethods->all([
            'customer' => $customerId,
            'limit' => 100,
        ]);

        $syncedStripeIds = [];

        foreach ($paymentMethods->autoPagingIterator() as $stripePaymentMethod) {
            $this->upsertPaymentMethod($stripePaymentMethod, $customerId, $billable, $defaultPaymentMethodId);
            $syncedStripeIds[] = $stripePaymentMethod->id;
        }

        // Remove local payment methods that are no longer in Stripe (detached)
        $removedCount = PaymentMethod::query()
            ->where('stripe_customer_id', $customerId)
            ->whereNotIn('stripe_id', $syncedStripeIds)
            ->delete();

        $this->removed += $removedCount;
    }

    /**
     * Create or update a local payment method from a Stripe payment method object.
     *
     * @param  \Stripe\PaymentMethod  $stripePaymentMethod
     * @param  array{billable_type: string, billable_id: int}  $billable
     */
    protected function upsertPaymentMethod(mixed $stripePaymentMethod, string $customerId, array $billable, ?string $defaultPaymentMethodId): void
    {
        $card = $stripePaymentMethod->card;

        $attributes = [
            'stripe_customer_id' => $customerId,
            'billable_type' => $billable['billable_type'],
            'billable_id' => $billable['billable_id'],
            'type' => $stripePaymentMethod->type ?? 'card',
            'brand' => $card->brand ?? null,
            'last4' => $card->last4 ?? null,
            'exp_month' => $card->exp_month ?? null,
            'exp_year' => $card->exp_year ?? null,
            'is_default' => $stripePaymentMethod->id === $defaultPaymentMethodId,
        ];

        $existing = PaymentMethod::query()->where('stripe_id', $stripePaymentMethod->id)->first();

        if ($existing) {
            $existing->update($attributes);
            $this->updated++;
        } else {
            PaymentMethod::create(array_merge(['stripe_id' => $stripePaymentMethod->id], $attributes));
            $this->created++;
        }
    }

    /**
     * Resolve the billable type and ID from a Stripe customer ID.
     *
     * @return array{billable_type: string, billable_id: int}|null
     */
    protected function resolveBillable(string $stripeCustomerId): ?array
    {
        $user = User::query()->where('stripe_id', $stripeCustomerId)->first(['id']);

        if ($user) {
            return ['billable_type' => 'user', 'billable_id' => $user->id];
        }

        return null;
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
