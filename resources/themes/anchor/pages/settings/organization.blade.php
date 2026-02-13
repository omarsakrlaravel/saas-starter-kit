<?php

    use App\Mail\OrganizationInvite;
    use App\Models\Organization;
    use App\Models\User;
    use Filament\Actions\Action;
    use Filament\Actions\Concerns\InteractsWithActions;
    use Filament\Actions\Contracts\HasActions;
    use Filament\Notifications\Notification;
    use Filament\Schemas\Concerns\InteractsWithSchemas;
    use Filament\Schemas\Contracts\HasSchemas;
    use Illuminate\Support\Facades\Mail;
    use Illuminate\Support\Facades\URL;
    use Illuminate\Support\Str;
    use Livewire\Volt\Component;
    use Wave\Actions\Billing\Stripe\UpdateSubscriptionQuantity;
    use function Laravel\Folio\{middleware, name};

    middleware(['auth', 'verified']);
    name('settings.organization');

    new class extends Component implements HasActions, HasSchemas
    {
        use InteractsWithActions;
        use InteractsWithSchemas;

        public string $organizationName = '';
        public string $editName = '';
        public string $inviteEmail = '';
        public int $targetSeats = 1;

        public function mount(): void
        {
            $org = $this->currentOrganization();
            if ($org) {
                $this->editName = $org->name;
            }
        }

        public function createOrganization(): void
        {
            $this->validate(['organizationName' => 'required|string|max:255']);

            $slug = Str::slug($this->organizationName);
            $slug = $slug === '' ? 'organization' : $slug;
            $baseSlug = $slug;
            $counter = 1;
            while (Organization::where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$counter++;
            }

            $organization = Organization::create([
                'name' => $this->organizationName,
                'slug' => $slug,
                'owner_user_id' => auth()->id(),
                'active' => true,
            ]);

            auth()->user()->setBillingContext($organization->id);
            auth()->user()->save();

            $this->organizationName = '';
            $this->editName = $organization->name;

            Notification::make()
                ->title('Organization created successfully')
                ->success()
                ->send();
        }

        public function updateName(): void
        {
            $this->validate(['editName' => 'required|string|max:255']);

            $org = $this->currentOrganization();
            if (! $org || ! $this->isOwner()) {
                return;
            }

            $org->update(['name' => $this->editName]);

            Notification::make()
                ->title('Organization name updated')
                ->success()
                ->send();
        }

        public function deleteOrganizationAction(): Action
        {
            return Action::make('deleteOrganization')
                ->label('Delete Organization')
                ->icon('phosphor-trash-duotone')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Delete Organization')
                ->modalDescription('Are you sure you want to permanently delete this organization? All members will be removed and subscriptions cancelled. This cannot be undone.')
                ->modalSubmitActionLabel('Yes, delete organization')
                ->action(function (): void {
                    $org = $this->currentOrganization();
                    if (! $org || ! $this->isOwner()) {
                        return;
                    }

                    $org->members()->detach();
                    $org->subscriptions()->delete();
                    $org->delete();

                    auth()->user()->setBillingContext(null);
                    auth()->user()->save();

                    $this->editName = '';

                    Notification::make()
                        ->title('Organization deleted')
                        ->success()
                        ->send();

                    $this->redirect(route('settings.organization'), navigate: true);
                });
        }

        public function removeMemberAction(): Action
        {
            return Action::make('removeMember')
                ->iconButton()
                ->icon('phosphor-x-bold')
                ->color('gray')
                ->size('sm')
                ->requiresConfirmation()
                ->modalHeading('Remove Member')
                ->modalDescription('Remove this member from the organization?')
                ->modalSubmitActionLabel('Remove')
                ->action(function (array $arguments): void {
                    $userId = (int) $arguments['userId'];
                    $org = $this->currentOrganization();
                    if (! $org || ! $this->isOwner()) {
                        return;
                    }

                    if ($userId === auth()->id()) {
                        return;
                    }

                    $org->members()->detach($userId);

                    Notification::make()
                        ->title('Member removed')
                        ->success()
                        ->send();
                });
        }

        public function revokeInviteAction(): Action
        {
            return Action::make('revokeInvite')
                ->iconButton()
                ->icon('phosphor-x-bold')
                ->color('gray')
                ->size('sm')
                ->requiresConfirmation()
                ->modalHeading('Revoke Invitation')
                ->modalDescription('Revoke this invitation?')
                ->modalSubmitActionLabel('Revoke')
                ->action(function (array $arguments): void {
                    $userId = (int) $arguments['userId'];
                    $org = $this->currentOrganization();
                    if (! $org || ! $this->isOwner()) {
                        return;
                    }

                    $org->members()->wherePivot('status', 'invited')->detach($userId);

                    Notification::make()
                        ->title('Invite revoked')
                        ->success()
                        ->send();
                });
        }

        public function leaveOrganizationAction(): Action
        {
            return Action::make('leaveOrganization')
                ->label('Leave organization')
                ->link()
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Leave Organization')
                ->modalDescription('Are you sure you want to leave this organization? You will lose access to its subscription and resources.')
                ->modalSubmitActionLabel('Yes, leave organization')
                ->action(function (): void {
                    $org = $this->currentOrganization();
                    if (! $org || $this->isOwner()) {
                        return;
                    }

                    $org->members()->detach(auth()->id());
                    auth()->user()->setBillingContext(null);
                    auth()->user()->save();

                    Notification::make()
                        ->title('You have left the organization')
                        ->success()
                        ->send();

                    $this->redirect(route('settings.organization'), navigate: true);
                });
        }

        public function openManageSeats(): void
        {
            $org = $this->currentOrganization();
            $subscription = $org?->activeSubscription();
            $this->targetSeats = $subscription?->seats ?? 1;
            $this->dispatch('open-modal', id: 'manage-seats');
        }

        public function updateSeats(): void
        {
            $org = $this->currentOrganization();
            if (! $org || ! $this->isOwner()) {
                return;
            }

            $subscription = $org->activeSubscription();
            if (! $subscription) {
                return;
            }

            $this->validate([
                'targetSeats' => 'required|integer|min:1|max:100',
            ]);

            $occupied = $org->occupiedSeatCount();
            if ($this->targetSeats < $occupied) {
                $this->addError('targetSeats', "Cannot reduce below {$occupied} occupied " . Str::plural('seat', $occupied) . '.');

                return;
            }

            $delta = $this->targetSeats - $subscription->seats;
            if ($delta === 0) {
                $this->dispatch('close-modal', id: 'manage-seats');

                return;
            }

            try {
                app(UpdateSubscriptionQuantity::class)($subscription, $delta);
            } catch (\RuntimeException $e) {
                Notification::make()
                    ->title('Failed to update seats. Please try again.')
                    ->danger()
                    ->send();

                return;
            }

            Notification::make()
                ->title('Seats updated successfully.')
                ->success()
                ->send();

            $this->dispatch('close-modal', id: 'manage-seats');
        }

        public function inviteMember(): void
        {
            $org = $this->currentOrganization();
            if (! $org || ! $this->isOwner()) {
                return;
            }

            $this->validate(['inviteEmail' => 'required|email|max:255']);
            $this->inviteEmail = strtolower(trim($this->inviteEmail));
            if (! $org->hasAvailableSeatForNewInvite()) {
                $this->addError('inviteEmail', 'No available seats. Add seats first.');
                return;
            }

            $existingMember = $org->members()
                ->where('email', $this->inviteEmail)
                ->first();

            if ($existingMember) {
                $this->addError('inviteEmail', 'This person is already a member or has a pending invite.');
                return;
            }

            $invitedUser = User::where('email', $this->inviteEmail)->first();
            if (! $invitedUser) {
                $name = ucwords(str_replace('.', ' ', Str::before($this->inviteEmail, '@')));
                $invitedUser = User::create([
                    'name' => $name,
                    'email' => $this->inviteEmail,
                    'password' => bcrypt(Str::random(32)),
                    'email_verified_at' => now(),
                ]);
            }

            $org->members()->syncWithoutDetaching([
                $invitedUser->id => [
                    'role' => 'member',
                    'status' => 'invited',
                    'invited_by' => auth()->id(),
                    'invited_at' => now(),
                ],
            ]);

            $acceptUrl = URL::signedRoute(
                'organization.invite.accept',
                [
                    'organization' => $org->id,
                    'email' => $this->inviteEmail,
                ],
                now()->addDays(7)
            );

            Mail::to($this->inviteEmail)->send(new OrganizationInvite($org, $acceptUrl));

            $this->inviteEmail = '';

            Notification::make()
                ->title('Invitation sent')
                ->success()
                ->send();
        }

        public function currentOrganization(): ?Organization
        {
            $orgId = auth()->user()->current_organization_id;
            if (! $orgId) {
                return null;
            }

            return auth()->user()->organizations()
                ->where('organizations.id', $orgId)
                ->where('organizations.active', true)
                ->wherePivot('status', 'active')
                ->first();
        }

        public function isOwner(): bool
        {
            $org = $this->currentOrganization();
            if (! $org) {
                return false;
            }

            return $org->pivot->role === 'owner';
        }
    }

?>

<x-layouts.app>
    @volt('settings.organization')
        <div class="relative">
            <x-app.settings-layout
                title="Organization"
                description="Manage your organization and team members."
            >
                @php
                    $user = auth()->user();
                    $org = $this->currentOrganization();
                    $isOwner = $this->isOwner();
                @endphp

                @if(! $org)
                    {{-- No org — create form --}}
                    <div class="p-6 text-center bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                        <x-phosphor-buildings-duotone class="w-12 h-12 mx-auto text-zinc-400" />
                        <h3 class="mt-3 text-base font-semibold text-zinc-900 dark:text-zinc-100">Create Organization</h3>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Create an organization to collaborate with your team.</p>
                        <form wire:submit="createOrganization" class="flex flex-col gap-3 mt-4 sm:flex-row sm:items-start max-w-md mx-auto">
                            <div class="flex-1">
                                <input
                                    type="text"
                                    wire:model="organizationName"
                                    placeholder="Organization name"
                                    class="w-full rounded-md border border-zinc-300 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100 text-sm"
                                />
                                @error('organizationName')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <x-button type="submit">Create</x-button>
                        </form>
                    </div>
                @else
                    {{-- Org header: name + settings gear --}}
                    @php
                        $activeMemberCount = $org->activeMemberCount();
                        $invitedMemberCount = $org->invitedMemberCount();
                        $seatUsage = $org->occupiedSeatCount();
                        $activeSubscription = $org->activeSubscription();
                        $totalSeats = $activeSubscription?->seats ?? 0;
                        $availableSeats = max($totalSeats - $seatUsage, 0);
                        $usagePercent = $totalSeats > 0 ? min(($seatUsage / $totalSeats) * 100, 100) : 0;
                    @endphp

                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-3">
                            <span class="flex items-center justify-center w-10 h-10 rounded-lg text-base font-bold bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900">
                                {{ strtoupper(substr($org->name, 0, 1)) }}
                            </span>
                            <div>
                                <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $org->name }}</h3>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $activeMemberCount }} {{ Str::plural('member', $activeMemberCount) }}
                                    @if($invitedMemberCount > 0)
                                        · {{ $invitedMemberCount }} pending
                                    @endif
                                </p>
                            </div>
                        </div>
                        @if($isOwner)
                            <button
                                @click="$dispatch('open-modal', { id: 'org-settings' })"
                                class="flex items-center justify-center w-9 h-9 rounded-lg text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 dark:hover:text-zinc-200 dark:hover:bg-zinc-700/60 transition-colors"
                                title="Organization settings"
                            >
                                <x-phosphor-gear-duotone class="w-5 h-5" />
                            </button>
                        @endif
                    </div>

                    {{-- Seats and Invitations (owner only) --}}
                    @if($isOwner)
                        <div class="mb-6 grid gap-4 grid-cols-1">
                            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800/80 overflow-hidden">
                                <div class="px-3 py-2.5 border-b border-zinc-200/80 dark:border-zinc-700/80 flex items-start justify-between">
                                    <div class="flex items-center gap-2.5">
                                        <div>
                                            <h4 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Team Seats</h4>
                                            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $totalSeats }} {{ Str::plural('seat', $totalSeats) }} total</p>
                                        </div>
                                    </div>

                                    @if($activeSubscription)
                                        <button
                                            wire:click="openManageSeats"
                                            class="inline-flex items-center gap-1.5 rounded-md border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900/70 px-3 py-1.5 text-xs font-medium text-zinc-700 dark:text-zinc-200 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors"
                                        >
                                            <x-phosphor-sliders-horizontal-bold class="w-3.5 h-3.5" />
                                            Manage seats
                                        </button>
                                    @else
                                        <a
                                            href="{{ route('settings.subscription') }}"
                                            wire:navigate
                                            class="inline-flex items-center gap-1.5 rounded-md border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900/70 px-3 py-1.5 text-xs font-medium text-zinc-700 dark:text-zinc-200 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors"
                                        >
                                            Add Billing
                                        </a>
                                    @endif
                                </div>

                                <div class="p-3 space-y-3">
                                    <div class="grid grid-cols-3 gap-2 text-xs">
                                        <div class="rounded-md border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900/80 px-2.5 py-2">
                                            <p class="text-zinc-500 dark:text-zinc-400">Used</p>
                                            <p class="mt-1 text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $seatUsage }}</p>
                                        </div>
                                        <div class="rounded-md border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900/80 px-2.5 py-2">
                                            <p class="text-zinc-500 dark:text-zinc-400">Available</p>
                                            <p class="mt-1 text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $availableSeats }}</p>
                                        </div>
                                        <div class="rounded-md border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900/80 px-2.5 py-2">
                                            <p class="text-zinc-500 dark:text-zinc-400">Active members</p>
                                            <p class="mt-1 text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $activeMemberCount }}</p>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="flex items-center justify-between text-xs mb-1.5">
                                            <span class="text-zinc-600 dark:text-zinc-400">Usage</span>
                                            <span class="text-zinc-500 dark:text-zinc-400">{{ $seatUsage }}/{{ $totalSeats }}</span>
                                        </div>
                                        <div class="w-full h-2 rounded-full bg-zinc-200 dark:bg-zinc-700 overflow-hidden">
                                            <div class="h-full rounded-full transition-all duration-300 {{ $usagePercent >= 100 ? 'bg-amber-500' : 'bg-zinc-900 dark:bg-zinc-300' }}" style="width: {{ $usagePercent }}%"></div>
                                        </div>
                                        @if(! $activeSubscription)
                                            <p class="mt-2 text-xs text-amber-600 dark:text-amber-400">
                                                Configure billing before adding members.
                                            </p>
                                        @elseif($availableSeats <= 0)
                                            <p class="mt-2 text-xs text-amber-600 dark:text-amber-400">
                                                No seats left. Increase seats first to invite or reactivate invites.
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800/80 overflow-hidden">
                                <div class="p-3">
                                    <form wire:submit="inviteMember" class="flex items-start gap-2">
                                        <div class="flex-1 relative">
                                            <input
                                                type="email"
                                                wire:model="inviteEmail"
                                                placeholder="Invite by email address..."
                                                class="w-full rounded-md border border-zinc-200 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 text-sm px-3 py-1.5 placeholder:text-zinc-400"
                                                @if(! $activeSubscription || ! $org->hasAvailableSeatForNewInvite()) disabled @endif
                                            />
                                            @error('inviteEmail')
                                                <p class="absolute -bottom-5 left-0 text-xs text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <button
                                            type="submit"
                                            class="inline-flex items-center gap-1.5 rounded-md bg-zinc-900 dark:bg-zinc-100 px-3.5 py-1.5 text-sm font-medium text-white dark:text-zinc-900 hover:bg-zinc-800 dark:hover:bg-zinc-200 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                            @if(! $activeSubscription || ! $org->hasAvailableSeatForNewInvite()) disabled @endif
                                            wire:loading.attr="disabled"
                                        >
                                            <x-phosphor-paper-plane-tilt-bold class="w-3.5 h-3.5" />
                                            Invite
                                        </button>
                                    </form>
                                    @if(! $activeSubscription)
                                        <p class="mt-6 text-xs text-amber-600 dark:text-amber-400">
                                            Billing is required before inviting a team member.
                                        </p>
                                    @elseif(! $org->hasAvailableSeatForNewInvite())
                                        <p class="mt-6 text-xs text-amber-600 dark:text-amber-400">
                                            All purchased seats are currently used. <button wire:click="openManageSeats" class="underline hover:no-underline">Add seats</button> to send more invites.
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Members list --}}
                    <div class="bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden">
                        <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach($org->members()->orderByPivot('role')->orderByPivot('joined_at')->get() as $member)
                                <li class="flex items-center justify-between px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <img src="{{ $member->avatar() }}" class="w-8 h-8 rounded-full" alt="">
                                        <div>
                                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $member->name }}</p>
                                            <p class="text-xs text-zinc-500">{{ $member->email }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium
                                            {{ $member->pivot->role === 'owner' ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300' }}">
                                            {{ ucfirst($member->pivot->role) }}
                                        </span>
                                        @if($member->pivot->status === 'invited')
                                            <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                                Pending
                                            </span>
                                        @endif
                                        @if($isOwner && $member->id !== $user->id)
                                            @if($member->pivot->status === 'invited')
                                                {{ ($this->revokeInviteAction)(['userId' => $member->id]) }}
                                            @else
                                                {{ ($this->removeMemberAction)(['userId' => $member->id]) }}
                                            @endif
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    {{-- Leave org (member only) --}}
                    @if(! $isOwner)
                        <div class="mt-6 pt-6 border-t border-zinc-200 dark:border-zinc-700">
                            {{ $this->leaveOrganizationAction }}
                        </div>
                    @endif

                    {{-- Settings modal (owner only) --}}
                    @if($isOwner)
                        <x-filament::modal id="org-settings" width="md">
                            <x-slot name="heading">Organization Settings</x-slot>

                            <div class="space-y-6">
                                {{-- Rename --}}
                                <div>
                                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">Organization Name</label>
                                    <form wire:submit="updateName" class="flex gap-2">
                                        <input
                                            type="text"
                                            wire:model="editName"
                                            class="flex-1 rounded-lg border border-zinc-300 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100 text-sm"
                                        />
                                        <x-button type="submit">Save</x-button>
                                    </form>
                                </div>

                                {{-- Danger Zone --}}
                                <div class="pt-4 border-t border-zinc-200 dark:border-zinc-700">
                                    <h4 class="text-sm font-medium text-red-600 dark:text-red-400">Danger Zone</h4>
                                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Permanently delete this organization, its members, and all associated subscriptions. This action cannot be undone.</p>
                                    <div class="mt-3">
                                        {{ $this->deleteOrganizationAction }}
                                    </div>
                                </div>
                            </div>
                        </x-filament::modal>

                        @if($activeSubscription)
                            @php
                                $plan = $activeSubscription->plan;
                                $pricePerSeat = $activeSubscription->cycle === 'year'
                                    ? (float) $plan->yearly_price
                                    : (float) $plan->monthly_price;
                                $cycleLabel = $activeSubscription->cycle === 'year' ? 'yr' : 'mo';
                                $minSeats = max($seatUsage, 1);
                            @endphp

                            <x-filament::modal id="manage-seats" width="md">
                                <x-slot name="heading">Manage Seats</x-slot>

                                <div
                                    x-data="{
                                        seats: @entangle('targetSeats'),
                                        min: {{ $minSeats }},
                                        max: 100,
                                        price: {{ $pricePerSeat }},
                                        occupied: {{ $seatUsage }},
                                    }"
                                    class="space-y-5"
                                >
                                    {{-- Current plan --}}
                                    <div class="flex items-center justify-between rounded-lg bg-zinc-50 dark:bg-zinc-800 px-4 py-3">
                                        <div class="flex items-center gap-2.5">
                                            <div class="flex items-center justify-center w-8 h-8 rounded-md bg-zinc-900 dark:bg-zinc-100">
                                                <x-phosphor-stack-duotone class="w-4 h-4 text-white dark:text-zinc-900" />
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $plan->name }}</p>
                                                <p class="text-xs text-zinc-500 dark:text-zinc-400">${{ number_format($pricePerSeat, 2) }}/{{ $cycleLabel }} per seat</p>
                                            </div>
                                        </div>
                                        <a href="{{ route('settings.subscription') }}" class="text-xs text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200 transition-colors" wire:navigate>
                                            Change plan &rarr;
                                        </a>
                                    </div>

                                    {{-- Seat counter --}}
                                    <div>
                                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Number of seats</label>
                                        <div class="flex items-center gap-3">
                                            <button
                                                @click="seats = Math.max(min, seats - 1)"
                                                :disabled="seats <= min"
                                                class="flex items-center justify-center w-10 h-10 rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                                                type="button"
                                            >
                                                <x-phosphor-minus-bold class="w-4 h-4" />
                                            </button>
                                            <input
                                                x-model.number="seats"
                                                type="number"
                                                :min="min"
                                                :max="max"
                                                class="flex-1 text-center text-2xl font-semibold rounded-lg border border-zinc-300 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100 py-2 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                            />
                                            <button
                                                @click="seats = Math.min(max, seats + 1)"
                                                :disabled="seats >= max"
                                                class="flex items-center justify-center w-10 h-10 rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                                                type="button"
                                            >
                                                <x-phosphor-plus-bold class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </div>

                                    {{-- Usage indicator --}}
                                    <div>
                                        <div class="flex items-center justify-between text-xs mb-1.5">
                                            <span class="text-zinc-600 dark:text-zinc-400">
                                                <span x-text="occupied"></span> occupied
                                            </span>
                                            <span class="text-zinc-500 dark:text-zinc-400" x-text="Math.max(0, seats - occupied) + ' available'"></span>
                                        </div>
                                        <div class="w-full h-1.5 rounded-full bg-zinc-200 dark:bg-zinc-700 overflow-hidden">
                                            <div
                                                class="h-full rounded-full transition-all duration-300"
                                                :class="seats <= occupied ? 'bg-amber-500' : 'bg-zinc-900 dark:bg-zinc-300'"
                                                :style="'width: ' + (seats > 0 ? Math.min((occupied / seats) * 100, 100) : 0) + '%'"
                                            ></div>
                                        </div>
                                    </div>

                                    {{-- Price summary --}}
                                    <div class="flex items-center justify-between rounded-lg bg-zinc-50 dark:bg-zinc-800 px-4 py-3 border border-zinc-200 dark:border-zinc-700">
                                        <span class="text-sm text-zinc-600 dark:text-zinc-400" x-text="seats + (seats === 1 ? ' seat' : ' seats') + ' × ${{ number_format($pricePerSeat, 2) }}/{{ $cycleLabel }}'"></span>
                                        <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                            $<span x-text="(seats * price).toFixed(2)"></span>/{{ $cycleLabel }}
                                        </span>
                                    </div>

                                    @error('targetSeats')
                                        <p class="text-xs text-red-600">{{ $message }}</p>
                                    @enderror

                                    {{-- Actions --}}
                                    <div class="flex items-center justify-end gap-3 pt-1">
                                        <button
                                            @click="$dispatch('close-modal', { id: 'manage-seats' })"
                                            type="button"
                                            class="rounded-lg px-4 py-2 text-sm font-medium text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            wire:click="updateSeats"
                                            wire:loading.attr="disabled"
                                            type="button"
                                            class="inline-flex items-center gap-2 rounded-lg bg-zinc-900 dark:bg-zinc-100 px-4 py-2 text-sm font-medium text-white dark:text-zinc-900 hover:bg-zinc-800 dark:hover:bg-zinc-200 transition-colors disabled:opacity-50"
                                        >
                                            <span wire:loading.remove wire:target="updateSeats">Update Seats</span>
                                            <span wire:loading wire:target="updateSeats">Updating...</span>
                                        </button>
                                    </div>
                                </div>
                            </x-filament::modal>
                        @endif
                    @endif
                @endif

            </x-app.settings-layout>

            <x-filament-actions::modals />
        </div>
    @endvolt
</x-layouts.app>
