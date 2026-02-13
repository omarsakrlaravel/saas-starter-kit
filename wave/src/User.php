<?php

namespace Wave;

use Carbon\Carbon;
use Devdojo\Auth\Models\User as AuthUser;
use Exception;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lab404\Impersonate\Models\Impersonate;
use Spatie\Permission\Traits\HasRoles;
use Stripe\StripeClient;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Wave\Traits\HasPlanFeatures;

class User extends AuthUser implements FilamentUser, HasAvatar, JWTSubject
{
    use HasPlanFeatures, HasRoles, Impersonate, Notifiable;

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
        ];
    }

    public function onTrial()
    {
        if (is_null($this->trial_ends_at)) {
            return false;
        }
        if ($this->subscriber()) {
            return false;
        }

        return true;
    }

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
        return $this->belongsTo(\App\Models\Organization::class, 'current_organization_id');
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Organization::class, 'organization_user')
            ->withPivot(['role', 'status', 'invited_by', 'invited_at', 'joined_at'])
            ->withTimestamps();
    }

    protected function organizationsEnabled(): bool
    {
        return (bool) config('wave.organizations_enabled', true);
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
            ->where('status', 'active')
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

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'billable_id')->where('billable_type', 'user');
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
                        ->contains(fn (Subscription $subscription): bool => $subscription->status === 'active');
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
            ->contains(fn (Subscription $subscription): bool => $subscription->status === 'active');
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
                        ->where('status', 'active')
                        ->isNotEmpty();
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
            ->where('status', 'active')
            ->isNotEmpty();
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

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class, 'billable_id')
            ->where('billable_type', 'user')
            ->where('status', 'active')
            ->orderByDesc('created_at');
    }

    public function invoices()
    {
        $user_invoices = [];
        $subscriptions = $this->getResolvedActiveBillingSubscriptions()
            ->where('status', 'active');

        if ($subscriptions->isEmpty()) {
            return [];
        }

        if (config('wave.billing_provider') == 'stripe') {
            $stripe = new StripeClient(config('wave.stripe.secret_key'));
            foreach ($subscriptions as $subscription) {
                if (empty($subscription->vendor_customer_id)) {
                    continue;
                }

                $invoices = $stripe->invoices->all(['customer' => $subscription->vendor_customer_id, 'limit' => 100]);

                foreach ($invoices as $invoice) {
                    array_push($user_invoices, (object) [
                        'id' => $invoice->id,
                        'created' => Carbon::parse($invoice->created)->isoFormat('MMMM Do YYYY, h:mm:ss a'),
                        'total' => number_format(($invoice->total / 100), 2, '.', ' '),
                        'download' => $invoice->invoice_pdf,
                    ]);
                }
            }
        } else {
            $paddle_url = (config('wave.paddle.env') == 'sandbox') ? 'https://sandbox-api.paddle.com' : 'https://api.paddle.com';
            foreach ($subscriptions as $subscription) {
                if (empty($subscription->vendor_subscription_id)) {
                    continue;
                }

                $response = Http::withToken(config('wave.paddle.api_key'))->get($paddle_url.'/transactions', [
                    'subscription_id' => $subscription->vendor_subscription_id,
                ]);
                $responseJson = json_decode($response->body());
                if (empty($responseJson->data)) {
                    continue;
                }
                foreach ($responseJson->data as $invoice) {
                    array_push($user_invoices, (object) [
                        'id' => $invoice->id,
                        'created' => Carbon::parse($invoice->created_at)->isoFormat('MMMM Do YYYY, h:mm:ss a'),
                        'total' => number_format(($invoice->details->totals->subtotal / 100), 2, '.', ' '),
                        'download' => '/settings/invoices/'.$invoice->id,
                    ]);
                }
            }
        }

        return $user_invoices;
    }

    public function canImpersonate(): bool
    {
        // If user is admin they can impersonate
        return $this->hasRole('admin');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function canBeImpersonated(): bool
    {
        // Any user that is not an admin can be impersonated
        return ! $this->hasRole('admin');
    }

    public function hasChangelogNotifications()
    {
        // Get the latest Changelog
        $latest_changelog = Changelog::orderByDesc('created_at')->first();

        if (! $latest_changelog) {
            return false;
        }

        return ! $this->changelogs->contains($latest_changelog->id);
    }

    public function link()
    {
        return url('/profile/'.$this->username);
    }

    public function changelogs(): BelongsToMany
    {
        return $this->belongsToMany('Wave\Changelog');
    }

    public function createApiKey($name)
    {
        return ApiKey::create(['user_id' => $this->id, 'name' => $name, 'key' => Str::random(60)]);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany('Wave\ApiKey')->orderByDesc('created_at');
    }

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

    public function avatar()
    {
        return Storage::url($this->avatar);
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     */
    public function getJWTCustomClaims(): array
    {
        return [];
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

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin' && auth()->user()->hasRole('admin')) {
            return true;
        }

        return false;
    }
}
