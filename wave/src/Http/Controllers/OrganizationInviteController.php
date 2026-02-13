<?php

namespace Wave\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

        $user->setBillingContext($organization->id);
        $user->save();

        return redirect('/dashboard')->with([
            'message' => "You've joined {$organization->name}!",
            'message_type' => 'success',
        ]);
    }
}
