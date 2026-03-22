<?php

use App\Models\Plan;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Blade;

if (! function_exists('setting')) {
    function setting($key, $default = null)
    {
        return config('saas.settings.'.$key, $default);
    }
}

if (! function_exists('blade')) {
    function blade($string)
    {
        return Blade::render($string);
    }
}

if (! function_exists('getMorphAlias')) {
    /**
     * Get the morph alias for a given class.
     *
     * @param  string  $class
     * @return string|null
     */
    function getMorphAlias($class)
    {
        $morphMap = Relation::morphMap();
        $alias = array_search($class, $morphMap);

        return $alias ?: null;
    }
}

if (! function_exists('has_monthly_yearly_toggle')) {
    function has_monthly_yearly_toggle(): bool
    {
        $plans = Plan::where('active', 1)->get();
        $hasMonthly = false;
        $hasYearly = false;

        foreach ($plans as $plan) {
            if ($plan->active) {
                if (! empty($plan->monthly_price_id)) {
                    $hasMonthly = true;
                }
                if (! empty($plan->yearly_price_id)) {
                    $hasYearly = true;
                }
            }
        }

        // Return true if both monthly and yearly plans exist
        if ($hasMonthly && $hasYearly) {
            return true;
        }

        // Return false if either is missing
        return false;
    }
}

if (! function_exists('get_default_billing_cycle')) {
    function get_default_billing_cycle()
    {
        $plans = Plan::where('active', 1)->get();
        $hasMonthly = false;
        $hasYearly = false;

        foreach ($plans as $plan) {
            if (! empty($plan->monthly_price_id)) {
                $hasMonthly = true;
            }
            if (! empty($plan->yearly_price_id)) {
                $hasYearly = true;
            }
        }

        // Return 'Yearly' if only yearly ID is present
        if ($hasYearly && ! $hasMonthly) {
            return 'Yearly';
        }

        // Return null or a default value if neither is present
        return 'Monthly'; // or any default value you prefer
    }
}

if (! function_exists('currencySymbol')) {
    function currencySymbol(?string $code = null): string
    {
        $symbols = [
            'usd' => '$',
            'eur' => "\u{20AC}",
            'gbp' => "\u{00A3}",
            'jpy' => "\u{00A5}",
            'cad' => 'CA$',
            'aud' => 'A$',
            'chf' => 'CHF',
            'inr' => "\u{20B9}",
            'brl' => 'R$',
            'mxn' => 'MX$',
        ];

        $normalized = strtolower(trim($code ?? 'usd'));

        return $symbols[$normalized] ?? strtoupper($normalized);
    }
}
