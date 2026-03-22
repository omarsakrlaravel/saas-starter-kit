<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class Stripe extends Controller
{
    public function redirect_to_customer_portal(): RedirectResponse
    {
        $user = auth()->user();

        if (! $user->hasStripeId()) {
            return redirect()->back()->withErrors('No active subscription found.');
        }

        return $user->redirectToBillingPortal(route('settings.subscription'));
    }
}
