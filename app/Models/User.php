<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Services\FileService;
use App\Traits\HasPlanFeatures;
use App\Traits\HasProfileKeyValues;
use Carbon\Carbon;
use Devdojo\Auth\Models\User as AuthUser;
use Exception;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Lab404\Impersonate\Models\Impersonate;
use Laravel\Cashier\Billable;
use Laravel\Cashier\Cashier;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends AuthUser implements FilamentUser, HasAvatar
{
    use Billable {
        onTrial as cashierOnTrial;
    }
    use HasApiTokens, HasFactory, HasPlanFeatures, HasProfileKeyValues, HasRoles, Impersonate, Notifiable, SoftDeletes;

    public $guard_name = 'web';

    /**
     * Cached billing context.
     */
    protected ?array $resolvedBillingContext = null;

    /**
     * Cached membership role for the current organization billing context.
     */
    protected ?string $resolvedCurrentOrganizationRole = null;

    /**
     * Whether the current organization membership has been resolved.
     */
    protected bool $currentOrganizationMembershipResolved = false;

    /**
     * Cached active billing subscriptions for the current request.
     */
    protected ?Collection $resolvedActiveBillingSubscriptions = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'avatar',
        'password',
        'verification_code',
        'verified',
        'trial_ends_at',
        'current_organization_id',
        'stripe_id',
        'pm_type',
        'pm_last_four',
        'status',
        'status_reason',
        'status_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'status' => AccountStatus::class,
            'notification_preferences' => 'array',
            'social_links' => 'array',
            'privacy_settings' => 'array',
            'status_expires_at' => 'datetime',
            'deletion_scheduled_at' => 'datetime',
        ];
    }

    // =========================================================================
    // Trial & Subscription
    // =========================================================================

    public function onTrial(?string $subscription = null): bool
    {
        if ($subscription !== null) {
            return $this->cashierOnTrial($subscription);
        }

        if (is_null($this->trial_ends_at)) {
            return false;
        }

        if ($this->subscriber()) {
            return false;
        }

        return $this->trial_ends_at->isFuture();
    }

    public function subscriber()
    {
        // Use cache if available, otherwise direct query
        if (app()->bound('cache')) {
            try {
                $cacheKey = "user_subscriber_{$this->id}";
                $scopedCacheKey = "user_subscriber_{$this->id}_{$this->getBillingContextCacheSuffix()}";
                $query = function () {
                    return $this->getResolvedActiveBillingSubscriptions()
                        ->contains(fn (Subscription $subscription): bool => $subscription->valid());
                };

                if ($this->getBillingContext()['type'] === 'user') {
                    return $this->rememberBillingCacheValue(300, $cacheKey, $query);
                }

                return $this->rememberBillingCacheValue(300, $scopedCacheKey, $query);
            } catch (Exception $e) {
                // Fallback to direct query if cache fails
            }
        }

        return $this->getResolvedActiveBillingSubscriptions()
            ->contains(fn (Subscription $subscription): bool => $subscription->valid());
    }

    public function subscribedToPlan($planSlug)
    {
        // Use cache if available, otherwise direct query
        if (app()->bound('cache')) {
            try {
                $cacheKey = "user_plan_{$this->id}_{$planSlug}";
                $scopedCacheKey = "user_plan_{$this->id}_{$this->getBillingContextCacheSuffix()}_{$planSlug}";
                $query = function () use ($planSlug) {
                    $plan = Plan::getByName($planSlug);
                    if (! $plan) {
                        return false;
                    }

                    return $this->getResolvedActiveBillingSubscriptions()
                        ->where('plan_id', $plan->id)
                        ->contains(fn (Subscription $subscription): bool => $subscription->valid());
                };

                if ($this->getBillingContext()['type'] === 'user') {
                    return $this->rememberBillingCacheValue(300, $cacheKey, $query);
                }

                return $this->rememberBillingCacheValue(300, $scopedCacheKey, $query);
            } catch (Exception $e) {
                // Fallback to direct query if cache fails
            }
        }

        $plan = Plan::getByName($planSlug);
        if (! $plan) {
            return false;
        }

        return $this->getResolvedActiveBillingSubscriptions()
            ->where('plan_id', $plan->id)
            ->contains(fn (Subscription $subscription): bool => $subscription->valid());
    }

    public function plan()
    {
        $latest_subscription = $this->latestSubscription();
        if (! $latest_subscription) {
            return;
        }

        return Plan::find($latest_subscription->plan_id);
    }

    public function planInterval()
    {
        $latest_subscription = $this->latestSubscription();
        if (! $latest_subscription) {
            return;
        }

        return ($latest_subscription->cycle == 'month') ? 'Monthly' : 'Yearly';
    }

    public function latestSubscription()
    {
        return $this->activeBillingSubscription();
    }

    // =========================================================================
    // Organization & Billing Context
    // =========================================================================

    public function setCurrentOrganizationIdAttribute(?int $value): void
    {
        $normalizedValue = $value ?: null;
        $originalValue = $this->attributes['current_organization_id'] ?? null;

        if ((string) $originalValue !== (string) $normalizedValue) {
            $this->clearBillingCacheState();
            $this->currentOrganizationMembershipResolved = false;
            if ($this->exists) {
                $this->clearUserCache();
            }
        }

        $this->attributes['current_organization_id'] = $normalizedValue;
    }

    public function setBillingContext(?int $organizationId): void
    {
        $this->attributes['current_organization_id'] = $organizationId ?: null;
        $this->clearBillingCacheState();
        $this->clearUserCache();
    }

    public function currentOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_user')
            ->withPivot(['role', 'status', 'invited_by', 'invited_at', 'joined_at'])
            ->withTimestamps();
    }

    protected function organizationsEnabled(): bool
    {
        return (bool) config('saas.organizations_enabled', true);
    }

    protected function resolveCurrentOrganizationRole(): void
    {
        if (! $this->organizationsEnabled()) {
            $this->currentOrganizationMembershipResolved = true;
            $this->resolvedCurrentOrganizationRole = null;

            return;
        }

        if (! $this->currentOrganizationMembershipResolved && ! empty($this->current_organization_id)) {
            $this->resolvedCurrentOrganizationRole = $this->organizations()
                ->where('organizations.id', $this->current_organization_id)
                ->where('organizations.active', true)
                ->wherePivot('status', 'active')
                ->value('organization_user.role');
        }

        $this->currentOrganizationMembershipResolved = true;
    }

    protected function currentOrganizationBillingRole(): ?string
    {
        if (! $this->currentOrganizationMembershipResolved) {
            $this->resolveCurrentOrganizationRole();
        }

        return $this->resolvedCurrentOrganizationRole;
    }

    protected function belongsToCurrentOrganization(): bool
    {
        if (! $this->organizationsEnabled()) {
            return false;
        }

        if (! $this->currentOrganizationMembershipResolved) {
            $this->resolveCurrentOrganizationRole();
        }

        return $this->resolvedCurrentOrganizationRole !== null;
    }

    protected function getBillingContextCacheSuffix(): string
    {
        $scope = $this->getBillingContext();

        return "{$scope['type']}_{$scope['id']}";
    }

    public function getBillingContext(): array
    {
        if (is_null($this->resolvedBillingContext)) {
            if ($this->belongsToCurrentOrganization()) {
                $this->resolvedBillingContext = ['type' => 'organization', 'id' => $this->current_organization_id];
            } else {
                $this->resolvedBillingContext = ['type' => 'user', 'id' => $this->id];
            }
        }

        return $this->resolvedBillingContext;
    }

    public function canManageBillingContext(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $scope = $this->getBillingContext();

        if ($scope['type'] === 'user') {
            return true;
        }

        $role = $this->currentOrganizationBillingRole();

        return $role === 'owner';
    }

    protected function activeBillingSubscriptionQuery(): Builder
    {
        $scope = $this->getBillingContext();

        return Subscription::query()
            ->where('billable_type', $scope['type'])
            ->where('billable_id', $scope['id']);
    }

    protected function getResolvedActiveBillingSubscriptions(): Collection
    {
        if (is_null($this->resolvedActiveBillingSubscriptions)) {
            $this->resolvedActiveBillingSubscriptions = $this->activeBillingSubscriptionQuery()->get();
        }

        return $this->resolvedActiveBillingSubscriptions;
    }

    public function activeBillingSubscriptions(): Collection
    {
        return $this->getResolvedActiveBillingSubscriptions();
    }

    public function activeBillingSubscription()
    {
        return $this->getResolvedActiveBillingSubscriptions()
            ->filter(fn (Subscription $subscription): bool => $subscription->valid())
            ->sortByDesc('created_at')
            ->first();
    }

    protected function clearBillingCacheState(): void
    {
        $this->resolvedBillingContext = null;
        $this->resolvedCurrentOrganizationRole = null;
        $this->currentOrganizationMembershipResolved = false;
        $this->resolvedActiveBillingSubscriptions = null;
    }

    protected function getBillingCacheStoreSupportsTags(): bool
    {
        try {
            return Cache::getStore()->supportsTags();
        } catch (\Throwable) {
            return false;
        }
    }

    protected function rememberBillingCacheValue(int $ttl, string $cacheKey, callable $callback)
    {
        if ($this->getBillingCacheStoreSupportsTags()) {
            return Cache::tags(['billing', "user:{$this->id}"])->remember($cacheKey, $ttl, $callback);
        }

        return Cache::remember($cacheKey, $ttl, $callback);
    }

    protected function flushBillingCache(): void
    {
        if (! $this->getBillingCacheStoreSupportsTags()) {
            return;
        }

        Cache::tags(['billing', "user:{$this->id}"])->flush();
    }

    // =========================================================================
    // Account Status (from App)
    // =========================================================================

    public function isActiveAccount(): bool
    {
        return ! $this->isRestricted() && ! $this->isSuspended();
    }

    public function isRestricted(): bool
    {
        return $this->status?->isRestricted() ?? false;
    }

    public function isSuspended(): bool
    {
        return $this->status?->isSuspended() ?? false;
    }

    public function isBlockedFromSession(): bool
    {
        return $this->isRestricted() || $this->isSuspended() || $this->organizationIsBlocked();
    }

    public function statusDisplay(): string
    {
        return $this->status?->label() ?? AccountStatus::Active->label();
    }

    public function organizationIsBlocked(): bool
    {
        $organization = $this->currentOrganizationForContext();

        if ($organization === null) {
            return false;
        }

        return $organization->isRestricted() || $organization->isSuspended();
    }

    public function activeOrganizationOrSelf(): self|Organization
    {
        $organization = $this->currentOrganizationForContext();

        if ($organization?->isActiveAccount()) {
            return $organization;
        }

        return $this;
    }

    public function currentOrganizationForContext(): ?Organization
    {
        if (empty($this->current_organization_id)) {
            return null;
        }

        return $this->organizations()
            ->where('organizations.id', $this->current_organization_id)
            ->where('organizations.active', true)
            ->wherePivot('status', 'active')
            ->first();
    }

    public function currentOrganizationIdForContext(): ?int
    {
        return $this->currentOrganizationForContext()?->getKey();
    }

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeActiveOrRestricted(Builder $query): Builder
    {
        return $query->whereIn('status', [AccountStatus::Active->value, AccountStatus::Restricted->value]);
    }

    public function statusHistories(): MorphMany
    {
        return $this->morphMany(AccountStatusHistory::class, 'suspendable');
    }

    public function recordStatusTransition(AccountStatus $toStatus, ?string $reason = null, ?int $appliedById = null, ?Carbon $expiresAt = null): AccountStatusHistory
    {
        $history = $this->statusHistories()->create([
            'from_status' => $this->status?->value,
            'to_status' => $toStatus->value,
            'reason' => $reason,
            'applied_by' => $appliedById,
            'expires_at' => $expiresAt,
            'applied_at' => now(),
            'reference_code' => 'ACCT-'.strtoupper(Str::random(10)),
        ]);

        $this->update([
            'status' => $toStatus->value,
            'status_reason' => $reason,
            'status_expires_at' => $expiresAt,
        ]);

        return $history;
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'user_id')->orderByDesc('created_at');
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class, 'user_id')
            ->where('billable_type', 'user')
            ->orderByDesc('created_at');
    }

    public function localInvoices(): MorphMany
    {
        return $this->morphMany(Invoice::class, 'billable');
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'billable');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'user_id')->orderByDesc('created_at');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function changelogs(): BelongsToMany
    {
        return $this->belongsToMany(Changelog::class);
    }

    public function avatarFile(): MorphOne
    {
        return $this->morphOne(File::class, 'fileable');
    }

    // =========================================================================
    // Invoices (Stripe)
    // =========================================================================

    public function billingInvoices(): array
    {
        if (! $this->hasStripeId()) {
            return [];
        }

        try {
            $subscriptionOrgIdMap = $this->buildSubscriptionOrgIdMap();
            $billingContext = $this->getBillingContext();

            return $this->invoicesIncludingPending()->map(function ($invoice) use ($subscriptionOrgIdMap): object {
                $stripeInvoice = $invoice->asStripeInvoice();
                $downloadUrl = $stripeInvoice->hosted_invoice_url ?? $stripeInvoice->invoice_pdf ?? null;
                $currency = (string) ($stripeInvoice->currency ?? 'usd');
                $rawSubtotal = (int) ($stripeInvoice->subtotal ?? 0);
                $rawTax = (int) ($stripeInvoice->tax ?? 0);
                $rawDiscount = $this->extractInvoiceDiscountAmount($stripeInvoice);
                $rawTotal = (int) ($stripeInvoice->total ?? 0);
                $lineItems = $this->extractInvoiceLineItems($stripeInvoice, $currency);
                $status = strtolower((string) ($stripeInvoice->status ?? 'unknown'));
                $orgId = $this->resolveInvoiceOrgId($stripeInvoice, $subscriptionOrgIdMap);

                return (object) [
                    'id' => $invoice->id,
                    'number' => (string) ($stripeInvoice->number ?? $invoice->id),
                    'created' => $invoice->date()->isoFormat('MMMM Do YYYY, h:mm:ss a'),
                    'status' => $this->humanInvoiceStatus($status),
                    'status_value' => $status,
                    'reason' => $this->resolveInvoiceReason($stripeInvoice, $lineItems),
                    'line_items' => $lineItems,
                    'subtotal' => Cashier::formatAmount($rawSubtotal, $currency),
                    'discount' => Cashier::formatAmount($rawDiscount, $currency),
                    'tax' => Cashier::formatAmount($rawTax, $currency),
                    'total' => Cashier::formatAmount($rawTotal, $currency),
                    'calculation' => $this->buildInvoiceCalculationSummary($currency, $rawSubtotal, $rawDiscount, $rawTax, $rawTotal),
                    'download' => $downloadUrl,
                    'organization_id' => $orgId,
                ];
            })->filter(fn (object $invoice): bool => is_string($invoice->download) && $invoice->download !== '')
                ->filter(function (object $invoice) use ($billingContext): bool {
                    if ($billingContext['type'] === 'organization') {
                        return $invoice->organization_id === $billingContext['id'];
                    }

                    return $invoice->organization_id === null;
                })
                ->values()
                ->all();
        } catch (\Throwable $e) {
            logger()->error('billingInvoices failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return [];
        }
    }

    protected function buildSubscriptionOrgIdMap(): array
    {
        return Subscription::query()
            ->where('user_id', $this->id)
            ->where('billable_type', 'organization')
            ->whereNotNull('stripe_id')
            ->pluck('billable_id', 'stripe_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function resolveInvoiceOrgId(object $stripeInvoice, array $subscriptionOrgIdMap): ?int
    {
        $topLevelSub = (string) ($stripeInvoice->subscription ?? '');
        if ($topLevelSub !== '' && isset($subscriptionOrgIdMap[$topLevelSub])) {
            return $subscriptionOrgIdMap[$topLevelSub];
        }

        $lineItems = data_get($stripeInvoice, 'lines.data', []);
        if (! is_iterable($lineItems)) {
            return null;
        }

        foreach ($lineItems as $lineItem) {
            $subId = (string) (
                data_get($lineItem, 'parent.subscription_item_details.subscription')
                ?? data_get($lineItem, 'subscription', '')
            );
            if ($subId !== '' && isset($subscriptionOrgIdMap[$subId])) {
                return $subscriptionOrgIdMap[$subId];
            }
        }

        return null;
    }

    protected function extractInvoiceDiscountAmount(object $stripeInvoice): int
    {
        $discounts = $stripeInvoice->total_discount_amounts ?? null;
        if (! is_iterable($discounts)) {
            return 0;
        }

        $totalDiscount = 0;

        foreach ($discounts as $discount) {
            $totalDiscount += (int) data_get($discount, 'amount', 0);
        }

        return $totalDiscount;
    }

    protected function extractInvoiceLineItems(object $stripeInvoice, string $currency): array
    {
        $lineItems = data_get($stripeInvoice, 'lines.data', []);
        if (! is_iterable($lineItems)) {
            return [];
        }

        $formattedItems = [];

        foreach ($lineItems as $lineItem) {
            $rawAmount = (int) data_get($lineItem, 'amount', 0);
            $description = trim((string) data_get($lineItem, 'description', 'Subscription charge'));
            $isProration = (bool) (
                data_get($lineItem, 'parent.subscription_item_details.proration')
                ?? data_get($lineItem, 'parent.invoice_item_details.proration', false)
            );

            $formattedItems[] = [
                'description' => $description !== '' ? $description : 'Subscription charge',
                'amount' => Cashier::formatAmount($rawAmount, $currency),
                'raw_amount' => $rawAmount,
                'is_proration' => $isProration,
            ];
        }

        return $formattedItems;
    }

    protected function resolveInvoiceReason(object $stripeInvoice, array $lineItems): string
    {
        $hasProration = collect($lineItems)->contains(fn (array $lineItem): bool => $lineItem['is_proration'] === true);

        if ($hasProration) {
            return $this->summarizeProration($lineItems);
        }

        $descriptions = array_column($lineItems, 'description');
        $firstLine = $descriptions[0] ?? '';

        $billingReason = (string) ($stripeInvoice->billing_reason ?? '');

        if ($billingReason === 'subscription_create' && preg_match('/(\d+)\s*[×x]\s*(.+?)\s*\(/u', $firstLine, $m)) {
            $qty = (int) $m[1];
            $plan = trim($m[2]);

            return $qty > 1 ? "{$plan} - {$qty} seats" : $plan;
        }

        return match ($billingReason) {
            'subscription_create' => 'New subscription',
            'subscription_cycle' => 'Renewal',
            'subscription_update' => 'Subscription update',
            'manual' => 'Manual charge',
            default => 'Subscription charge',
        };
    }

    protected function summarizeProration(array $lineItems): string
    {
        $oldQty = null;
        $newQty = null;
        $plan = null;

        foreach ($lineItems as $item) {
            if (! $item['is_proration']) {
                continue;
            }

            $desc = $item['description'];

            if (preg_match('/Unused time on (\d+)\s*[×x]\s*(.+?)\s+after/iu', $desc, $m)) {
                $oldQty = (int) $m[1];
                $plan ??= trim($m[2]);
            } elseif (preg_match('/Remaining time on (\d+)\s*[×x]\s*(.+?)\s+after/iu', $desc, $m)) {
                $newQty = (int) $m[1];
                $plan ??= trim($m[2]);
            }
        }

        if ($oldQty !== null && $newQty !== null && $plan !== null) {
            return "{$plan}: {$oldQty} to {$newQty} seats";
        }

        if ($newQty !== null && $plan !== null) {
            return "Updated to {$newQty} seats on {$plan}";
        }

        return 'Prorated adjustment';
    }

    protected function humanInvoiceStatus(string $status): string
    {
        return match ($status) {
            'paid' => 'Paid',
            'open' => 'Open',
            'draft' => 'Draft',
            'void' => 'Void',
            'uncollectible' => 'Uncollectible',
            default => 'Unknown',
        };
    }

    protected function buildInvoiceCalculationSummary(
        string $currency,
        int $rawSubtotal,
        int $rawDiscount,
        int $rawTax,
        int $rawTotal
    ): string {
        $parts = ['Subtotal '.Cashier::formatAmount($rawSubtotal, $currency)];

        if ($rawDiscount > 0) {
            $parts[] = '- Discount '.Cashier::formatAmount($rawDiscount, $currency);
        }

        if ($rawTax > 0) {
            $parts[] = '+ Tax '.Cashier::formatAmount($rawTax, $currency);
        }

        $parts[] = '= Total '.Cashier::formatAmount($rawTotal, $currency);

        return implode(' ', $parts);
    }

    // =========================================================================
    // Avatar & Profile
    // =========================================================================

    public function avatar(): string
    {
        if (empty($this->attributes['avatar'])) {
            return url('storage/demo/default.png');
        }

        $file = $this->avatarFile;
        if ($file) {
            return app(FileService::class)->signedUrl($file);
        }

        return url('storage/demo/default.png');
    }

    public function scopeWithAvatarFile(Builder $query): Builder
    {
        return $query->with('avatarFile');
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar();
    }

    public function profile($key)
    {
        $keyValue = $this->profileKeyValue($key);

        return isset($keyValue->value) ? $keyValue->value : '';
    }

    public function link()
    {
        return url('/profile/'.$this->username);
    }

    // =========================================================================
    // Changelog Notifications
    // =========================================================================

    public function hasChangelogNotifications()
    {
        // Get the latest Changelog
        $latest_changelog = Changelog::orderByDesc('created_at')->first();

        if (! $latest_changelog) {
            return false;
        }

        return ! $this->changelogs->contains($latest_changelog->id);
    }

    // =========================================================================
    // API Keys
    // =========================================================================

    public function createApiKey(string $name): \Laravel\Sanctum\NewAccessToken
    {
        return $this->createToken($name);
    }

    public function apiKeys(): MorphMany
    {
        return $this->tokens();
    }

    // =========================================================================
    // Impersonation & Admin
    // =========================================================================

    public function canImpersonate(): bool
    {
        return $this->hasRole('admin');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function canBeImpersonated(): bool
    {
        return ! $this->hasRole('admin');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin' && auth()->user()->hasRole('admin')) {
            return true;
        }

        return false;
    }

    // =========================================================================
    // Cache Management
    // =========================================================================

    /**
     * Clear user-related caches when data changes
     */
    public function clearUserCache(?int $planId = null): void
    {
        $this->resolvedActiveBillingSubscriptions = null;
        $this->resolvedBillingContext = null;

        if (! app()->bound('cache')) {
            return;
        }

        if ($this->getBillingCacheStoreSupportsTags()) {
            $this->flushBillingCache();

            return;
        }

        try {
            Cache::forget("user_admin_{$this->id}");
            Cache::forget("user_subscriber_{$this->id}");
            Cache::forget("user_subscriber_{$this->id}_user_{$this->id}");

            if ($this->organizationsEnabled()) {
                $organizationIds = $this->organizations()->pluck('organizations.id');
                foreach ($organizationIds as $organizationId) {
                    Cache::forget("user_subscriber_{$this->id}_organization_{$organizationId}");
                }
            }

            if ($planId) {
                $plan = Plan::find($planId);
                if ($plan) {
                    $this->forgetPlanCacheKeys($plan->name);
                }
            } else {
                $plans = Plan::pluck('name');
                foreach ($plans as $planName) {
                    $this->forgetPlanCacheKeys($planName);
                }
            }
        } catch (Exception) {
            // Silently handle cache clearing failures
        }
    }

    protected function forgetPlanCacheKeys(string $planName): void
    {
        Cache::forget("user_plan_{$this->id}_{$planName}");
        Cache::forget("user_plan_{$this->id}_user_{$this->id}_{$planName}");

        if ($this->organizationsEnabled()) {
            $organizationIds = $this->organizations()->pluck('organizations.id');
            foreach ($organizationIds as $organizationId) {
                Cache::forget("user_plan_{$this->id}_organization_{$organizationId}_{$planName}");
            }
        }
    }

    // =========================================================================
    // Boot
    // =========================================================================

    protected static function boot()
    {
        parent::boot();

        // Listen for the creating event of the model
        static::creating(function ($user) {
            // Check if the username attribute is empty
            if (empty($user->username)) {
                // Use the name to generate a slugified username
                $username = Str::slug($user->name, '');
                $i = 1;
                while (self::where('username', $username)->exists()) {
                    $username = Str::slug($user->name, '').$i;
                    $i++;
                }
                $user->username = $username;
            }
        });
    }
}
