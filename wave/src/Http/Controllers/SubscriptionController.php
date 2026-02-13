<?php

namespace Wave\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Wave\Http\Controllers\Auth\RegisterController;
use Wave\Plan;
use Wave\Subscription;
use Wave\User;

class SubscriptionController extends Controller
{
    private $paddle_url;

    private $api_key;

    public function __construct()
    {
        $this->api_key = config('wave.paddle.api_key');

        $this->paddle_url = (config('wave.paddle.env') == 'sandbox') ? 'https://sandbox-api.paddle.com' : 'https://api.paddle.com';
    }

    public function subscribe(Request $request)
    {
        return $this->checkout($request);
    }

    public function cancel(Request $request): JsonResponse
    {
        [$status, $message] = $this->cancelSubscription($request->id);

        return response()->json(
            ['status' => $status ? 1 : 0, 'message' => $message],
            $status ? 200 : 422
        );
    }

    private function cancelSubscription($subscriptionId = null): array
    {
        // Auth user get latest subscription id
        $subscription = auth()->user()->latestSubscription();
        $subscriptionId = $subscriptionId ?: $subscription?->vendor_subscription_id;

        if (! $subscription || ! $subscriptionId) {
            return [false, 'No active subscription found.'];
        }

        if ($subscription->vendor_subscription_id !== (string) $subscriptionId) {
            return [false, 'Invalid subscription ID.'];
        }

        // Ensure the provided subscription ID matches the user's subscription ID
        $localSubscription = Subscription::where('vendor_subscription_id', $subscriptionId)->first();

        if (! $localSubscription) {
            return [false, 'Invalid subscription ID.'];
        }

        $response = Http::withToken($this->api_key)
            ->post($this->paddle_url.'/subscriptions/'.$subscriptionId.'/cancel', [
                'effective_from' => 'immediately',
            ]);

        Log::info($response->body());

        // Check if the request was successful
        if ($response->successful()) {
            $body = $response->json();

            if (isset($body['data']) && isset($body['data']['status']) && $body['data']['status'] == 'canceled') {

                // Update subscription in local database
                $localSubscription->cancelled_at = Carbon::parse($body['data']['canceled_at']);
                $localSubscription->status = 'cancelled';
                $localSubscription->save();

                $localSubscription->clearBillableCache();

                return [true, 'Your subscription has been successfully canceled.'];
            } else {
                // Handle any errors that were returned in the response body
                $error = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error while canceling the subscription.';

                return [false, $error];
            }
        } else {
            // Handle failed HTTP requests
            return [false, 'Failed to cancel the subscription. Please try again later.'];
        }
    }

    public function checkout(Request $request): JsonResponse
    {
        $retryCount = 5;
        $initialDelay = 2;
        $transaction = null;
        $status = 0;
        $message = '';
        $guest = (auth()->guest()) ? 1 : 0;

        for ($i = 0; $i < $retryCount; $i++) {
            $response = Http::withToken($this->api_key)->get($this->paddle_url.'/transactions/'.$request->checkout_id);

            Log::info($response->body());
            if ($response->successful()) {
                $resBody = json_decode($response->body());
                if (isset($resBody->data->status) && ! is_null($resBody->data->subscription_id)) {
                    $transaction = $resBody->data;
                    break;
                }
            }

            sleep($initialDelay * (2 ** $i));
        }

        if ($transaction) {
            // Proceed with processing the transaction
            $plans = Plan::all();
            if ($transaction->origin === 'web' && $plans->contains('plan_id', $transaction->items[0]->price->id)) {
                $subscriptionUser = Http::withToken($this->api_key)->get($this->paddle_url.'/subscriptions/'.$transaction->subscription_id);
                $subscriptionData = json_decode($subscriptionUser->body());
                $subscription = $subscriptionData->data;

                $customerResponse = Http::withToken($this->api_key)->get($this->paddle_url.'/customers/'.$subscription->customer_id);
                $customerData = json_decode($customerResponse->body());
                $customerEmail = $customerData->data->email;
                $customerName = $customerData->data->name;
                if (empty($customerName)) {
                    $nameParts = explode('@', $customerEmail);
                    $customerName = $nameParts[0];
                }

                if ($guest) {
                    if (User::where('email', $customerEmail)->exists()) {
                        $user = User::where('email', $customerEmail)->first();
                    } else {
                        $registration = new RegisterController();
                        $user_data = [
                            'name' => $customerName,
                            'email' => $customerEmail,
                            'password' => Hash::make(uniqid()),
                        ];
                        $user = $registration->create($user_data);
                        Auth::login($user);
                    }
                } else {
                    $user = auth()->user();
                }

                $plan = Plan::where('plan_id', $transaction->items[0]->price->id)->first();
                if (! isset($plan->id)) {
                    $message = 'Error locating that subscription product id. Please contact us if you think this is incorrect.';
                } else {
                    $billingContext = $user->getBillingContext();

                    Subscription::create([
                        'billable_type' => $billingContext['type'],
                        'billable_id' => $billingContext['id'],
                        'vendor_subscription_id' => $transaction->subscription_id,
                        'plan_id' => $plan->id,
                        'vendor_slug' => 'paddle',
                        'vendor_transaction_id' => $transaction->id,
                        'vendor_customer_id' => $subscription->customer_id,
                        'status' => $subscription->status,
                        'last_payment_at' => $subscription->first_billed_at,
                        'next_payment_at' => $subscription->next_billed_at,
                        'cancel_url' => $subscription->management_urls->cancel,
                        'update_url' => $subscription->management_urls->update_payment_method,
                        'cycle' => 'month',
                        'seats' => 1,
                    ]);

                    $status = 1;
                }
            } else {
                $message = 'Error locating that subscription product id. Please contact us if you think this is incorrect.';
            }
        } else {
            $message = 'Error processing the transaction. Please try again.';
        }

        return response()->json([
            'status' => $status,
            'message' => $message,
            'guest' => $guest,
        ]);
    }

    public function transactions(User $user)
    {

        // Check if user has a subscription
        $activeSubscription = $user->latestSubscription();
        if (! $activeSubscription) {
            return [];
        }

        $invoices = [];
        $response = Http::withToken($this->api_key)->get($this->paddle_url.'/transactions', [
            'subscription_id' => $activeSubscription->vendor_subscription_id,
        ]);

        $transactions = json_decode($response->body());

        return $transactions->data;

    }

    public function invoice(Request $request, $transactionId): RedirectResponse
    {

        $response = Http::withToken($this->api_key)->get($this->paddle_url.'/transactions/'.$transactionId.'/invoice');
        $invoice = json_decode($response->body());

        // redirect user to the invoice download URL
        return redirect()->to($invoice->data->url);
    }

    public function switchPlans(Request $request): RedirectResponse
    {
        $plan = Plan::where('plan_id', $request->plan_id)->first();
        $subscription = $request->user()->latestSubscription();

        if (! isset($plan->id)) {
            return redirect()->back()->with(['message' => 'Could not locate the selected plan.', 'message_type' => 'danger']);
        }

        if (! $subscription || empty($subscription->vendor_subscription_id)) {
            return redirect()->back()->with(['message' => 'No active subscription found to update.', 'message_type' => 'danger']);
        }

        // Update the user plan with Paddle
        $response = Http::withToken($this->api_key)->patch(
            $this->paddle_url.'/subscriptions/'.$subscription->vendor_subscription_id,
            [
                'items' => [
                    [
                        'price_id' => $plan->plan_id,
                        'quantity' => $subscription->seats,
                    ],
                ],
                'proration_billing_mode' => 'prorated_immediately',
            ]
        );

        if ($response->successful()) {
            $body = $response->json();

            if (isset($body['data']) && $body['data']['status'] == 'active') {
                // Update the subscription with the updated plan in the local database
                $subscription->update([
                    'plan_id' => $plan->id,
                ]);

                return redirect()->back()->with(['message' => 'Successfully switched to the '.$plan->name.' plan.', 'message_type' => 'success']);
            }
        }

        return redirect()->back()->with(['message' => 'Sorry, there was an issue updating your plan.', 'message_type' => 'danger']);
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
