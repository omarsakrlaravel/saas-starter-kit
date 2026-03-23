<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeSyncInvoices extends Command
{
    protected $signature = 'stripe:sync-invoices {--customer= : Sync only for specific Stripe customer ID}';

    protected $description = 'Backfill invoices from Stripe into the local database';

    protected int $created = 0;

    protected int $updated = 0;

    public function handle(): int
    {
        $stripe = $this->makeStripeClient();

        $customerFilter = $this->option('customer');

        try {
            if ($customerFilter) {
                $this->syncCustomerInvoices($stripe, $customerFilter);
            } else {
                $this->syncAllCustomerInvoices($stripe);
            }
        } catch (ApiErrorException $e) {
            $this->error('Stripe API error: '.$e->getMessage());

            return self::FAILURE;
        }

        $total = $this->created + $this->updated;
        $this->info("Synced {$total} invoices ({$this->created} created, {$this->updated} updated).");

        return self::SUCCESS;
    }

    /**
     * Sync invoices for all users that have a Stripe customer ID.
     */
    protected function syncAllCustomerInvoices(StripeClient $stripe): void
    {
        $users = User::query()
            ->whereNotNull('stripe_id')
            ->where('stripe_id', '!=', '')
            ->get(['id', 'stripe_id']);

        if ($users->isEmpty()) {
            $this->warn('No users with a Stripe customer ID found.');

            return;
        }

        $this->info("Found {$users->count()} users with Stripe IDs. Syncing invoices...");

        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        foreach ($users as $user) {
            try {
                $this->syncCustomerInvoices($stripe, $user->stripe_id);
            } catch (ApiErrorException $e) {
                $this->newLine();
                $this->warn("Failed to sync invoices for customer {$user->stripe_id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Sync all invoices for a single Stripe customer.
     */
    protected function syncCustomerInvoices(StripeClient $stripe, string $customerId): void
    {
        $billable = $this->resolveBillable($customerId);

        $invoices = $stripe->invoices->all([
            'customer' => $customerId,
            'limit' => 100,
        ]);

        foreach ($invoices->autoPagingIterator() as $stripeInvoice) {
            $this->upsertInvoice($stripeInvoice, $customerId, $billable);
        }
    }

    /**
     * Create or update a local invoice from a Stripe invoice object.
     *
     * @param  \Stripe\Invoice  $stripeInvoice
     * @param  array{billable_type: string, billable_id: int}|null  $billable
     */
    protected function upsertInvoice(mixed $stripeInvoice, string $customerId, ?array $billable): void
    {
        $subscriptionId = $this->resolveSubscriptionId($stripeInvoice->subscription);

        $paidAt = null;
        if (isset($stripeInvoice->status_transitions) && $stripeInvoice->status_transitions->paid_at) {
            $paidAt = Carbon::createFromTimestamp($stripeInvoice->status_transitions->paid_at);
        }

        $lineItems = null;
        if (isset($stripeInvoice->lines) && isset($stripeInvoice->lines->data)) {
            $lineItems = json_decode(json_encode($stripeInvoice->lines->data), true);
        }

        $attributes = [
            'stripe_customer_id' => $customerId,
            'billable_type' => $billable['billable_type'] ?? 'user',
            'billable_id' => $billable['billable_id'] ?? 0,
            'subscription_id' => $subscriptionId,
            'number' => $stripeInvoice->number,
            'status' => $stripeInvoice->status ?? 'draft',
            'currency' => $stripeInvoice->currency ?? 'usd',
            'amount_due' => $stripeInvoice->amount_due ?? 0,
            'amount_paid' => $stripeInvoice->amount_paid ?? 0,
            'amount_remaining' => $stripeInvoice->amount_remaining ?? 0,
            'subtotal' => $stripeInvoice->subtotal ?? 0,
            'tax' => $stripeInvoice->tax,
            'total' => $stripeInvoice->total ?? 0,
            'period_start' => $stripeInvoice->period_start ? Carbon::createFromTimestamp($stripeInvoice->period_start) : null,
            'period_end' => $stripeInvoice->period_end ? Carbon::createFromTimestamp($stripeInvoice->period_end) : null,
            'due_date' => $stripeInvoice->due_date ? Carbon::createFromTimestamp($stripeInvoice->due_date) : null,
            'paid_at' => $paidAt,
            'hosted_invoice_url' => $stripeInvoice->hosted_invoice_url,
            'invoice_pdf' => $stripeInvoice->invoice_pdf,
            'line_items' => $lineItems,
        ];

        $existing = Invoice::query()->where('stripe_id', $stripeInvoice->id)->first();

        if ($existing) {
            $existing->update($attributes);
            $this->updated++;
        } else {
            Invoice::create(array_merge(['stripe_id' => $stripeInvoice->id], $attributes));
            $this->created++;
        }
    }

    /**
     * Resolve the local subscription ID from a Stripe subscription ID.
     */
    protected function resolveSubscriptionId(?string $stripeSubscriptionId): ?int
    {
        if (! $stripeSubscriptionId) {
            return null;
        }

        return Subscription::query()
            ->where('stripe_id', $stripeSubscriptionId)
            ->value('id');
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

    protected function makeStripeClient(): StripeClient
    {
        return app(StripeClient::class);
    }
}
