<?php

namespace Wave\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Wave\Plan;

class SubscriptionController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 0,
            'message' => 'Checkout sessions are created from the subscription page.',
        ], 422);
    }

    public function cancel(Request $request): JsonResponse
    {
        $subscription = auth()->user()->latestSubscription();

        if (! $subscription || ! $subscription->valid()) {
            return response()->json(['status' => 0, 'message' => 'No active subscription found.'], 422);
        }

        try {
            $subscription->cancel();
            $subscription->clearBillableCache();

            return response()->json([
                'status' => 1,
                'message' => 'Your subscription has been canceled. You will have access until '.$subscription->ends_at->format('F j, Y').'.',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 0, 'message' => 'Failed to cancel the subscription. Please try again later.'], 422);
        }
    }

    public function switchPlans(Request $request): RedirectResponse
    {
        $plan = Plan::find($request->integer('plan_id'));
        $billingCycle = $request->input('billing_cycle', 'month');
        $subscription = $request->user()->latestSubscription();

        if (! isset($plan->id)) {
            return redirect()->back()->with(['message' => 'Could not locate the selected plan.', 'message_type' => 'danger']);
        }

        if (! $subscription || ! $subscription->valid()) {
            return redirect()->back()->with(['message' => 'No active subscription found to update.', 'message_type' => 'danger']);
        }

        $priceId = $billingCycle === 'year' ? $plan->yearly_price_id : $plan->monthly_price_id;
        if (empty($priceId)) {
            return redirect()->back()->with(['message' => 'The selected billing cycle is not available for this plan.', 'message_type' => 'danger']);
        }

        try {
            $subscription->swapAndInvoice($priceId);

            $subscription->update([
                'plan_id' => $plan->id,
                'cycle' => $billingCycle,
            ]);

            $subscription->clearBillableCache();

            return redirect()->back()->with(['message' => 'Successfully switched to the '.$plan->name.' plan.', 'message_type' => 'success']);
        } catch (\Throwable $e) {
            return redirect()->back()->with(['message' => 'Sorry, there was an issue updating your plan.', 'message_type' => 'danger']);
        }
    }

    public function setBillingContext(Request $request): RedirectResponse
    {
        $request->validate([
            'current_organization_id' => 'nullable|integer',
        ]);

        $organizationId = $request->integer('current_organization_id');
        $organizationId = $organizationId > 0 ? $organizationId : null;
        $organizationsEnabled = config('wave.organizations_enabled', true);
        $user = $request->user();

        if (! $organizationsEnabled) {
            $organizationId = null;
        } elseif ($organizationId !== null) {
            $canUseOrganization = $user->organizations()
                ->where('organizations.active', true)
                ->wherePivot('status', 'active')
                ->where('organizations.id', $organizationId)
                ->exists();

            if (! $canUseOrganization) {
                return redirect()->back()->with([
                    'message' => 'You do not belong to that organization.',
                    'message_type' => 'danger',
                ]);
            }
        }

        $user->setBillingContext($organizationId);
        $user->save();

        return redirect()->back()->with([
            'message' => 'Billing context updated successfully.',
            'message_type' => 'success',
        ]);
    }
}
