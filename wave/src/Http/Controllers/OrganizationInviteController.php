<?php

namespace Wave\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Wave\Actions\Billing\Stripe\UpdateSubscriptionQuantity;

class OrganizationInviteController extends Controller
{
    public function accept(Request $request, Organization $organization): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'This invitation link is invalid or has expired.');
        }

        $email = $request->query('email');
        if (! is_string($email)) {
            abort(403, 'This invitation link is invalid or has expired.');
        }

        if (! auth()->check()) {
            session(['org_invite_url' => $request->fullUrl()]);

            return redirect()->route('login');
        }

        $user = auth()->user();

        if ($user->email !== $email) {
            $invitedUser = User::where('email', $email)->first();
            if ($invitedUser && $invitedUser->id !== $user->id) {
                return redirect('/dashboard')->with([
                    'message' => 'This invitation was sent to a different email address.',
                    'message_type' => 'danger',
                ]);
            }
        }

        $membership = $organization->members()
            ->where('users.id', $user->id)
            ->first();

        if ($membership) {
            if ($membership->pivot->status === 'active') {
                return redirect('/dashboard')->with([
                    'message' => "You're already a member of {$organization->name}.",
                    'message_type' => 'info',
                ]);
            }

            $organization->members()->updateExistingPivot($user->id, [
                'status' => 'active',
                'joined_at' => now(),
            ]);
        } else {
            $organization->members()->attach($user->id, [
                'role' => 'member',
                'status' => 'active',
                'invited_by' => $organization->owner_user_id,
                'invited_at' => now(),
                'joined_at' => now(),
            ]);
        }

        $subscription = $organization->activeSubscription();
        if ($subscription) {
            try {
                app(UpdateSubscriptionQuantity::class)($subscription, 1);
            } catch (\RuntimeException $e) {
                if ($membership) {
                    $organization->members()->updateExistingPivot($user->id, [
                        'status' => 'invited',
                        'joined_at' => null,
                    ]);
                } else {
                    $organization->members()->detach($user->id);
                }

                return redirect('/dashboard')->with([
                    'message' => 'Unable to update subscription seats. Please contact the organization owner.',
                    'message_type' => 'danger',
                ]);
            }
        }

        $user->setBillingContext($organization->id);
        $user->save();

        return redirect('/dashboard')->with([
            'message' => "You've joined {$organization->name}!",
            'message_type' => 'success',
        ]);
    }
}
