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

                    $subscription = $org->activeSubscription();
                    if ($subscription) {
                        try {
                            app(UpdateSubscriptionQuantity::class)($subscription, -1);
                        } catch (\RuntimeException $e) {
                            $org->members()->attach($userId, [
                                'role' => 'member',
                                'status' => 'active',
                                'invited_by' => auth()->id(),
                                'joined_at' => now(),
                            ]);

                            Notification::make()
                                ->title('Failed to update subscription seats. Member was not removed.')
                                ->danger()
                                ->send();

                            return;
                        }
                    }

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

                    $subscription = $org->activeSubscription();
                    if ($subscription) {
                        try {
                            app(UpdateSubscriptionQuantity::class)($subscription, -1);
                        } catch (\RuntimeException $e) {
                            Notification::make()
                                ->title('Failed to update subscription seats. Please try again.')
                                ->danger()
                                ->send();

                            return;
                        }
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

        public function inviteMember(): void
        {
            $org = $this->currentOrganization();
            if (! $org || ! $this->isOwner()) {
                return;
            }

            $this->validate(['inviteEmail' => 'required|email|max:255']);
            $this->inviteEmail = strtolower(trim($this->inviteEmail));

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
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-3">
                            <span class="flex items-center justify-center w-10 h-10 rounded-lg text-base font-bold bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900">
                                {{ strtoupper(substr($org->name, 0, 1)) }}
                            </span>
                            <div>
                                <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $org->name }}</h3>
                                @php
                                    $activeMemberCount = $org->members()->wherePivot('status', 'active')->count();
                                    $activeSubscription = $org->activeSubscription();
                                @endphp
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $activeMemberCount }} {{ Str::plural('member', $activeMemberCount) }}@if($activeSubscription) · {{ $activeSubscription->seats }} {{ Str::plural('seat', $activeSubscription->seats) }} on plan @endif
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

                    {{-- Invite member (owner only) --}}
                    @if($isOwner)
                        <div class="mb-6">
                            <form wire:submit="inviteMember" class="flex flex-col gap-3 sm:flex-row sm:items-start">
                                <div class="flex-1">
                                    <input
                                        type="email"
                                        wire:model="inviteEmail"
                                        placeholder="Invite by email address..."
                                        class="w-full rounded-lg border border-zinc-200 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 text-sm px-4 py-2.5 placeholder:text-zinc-400"
                                    />
                                    @error('inviteEmail')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <x-button type="submit">Invite</x-button>
                            </form>
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
                    @endif
                @endif

            </x-app.settings-layout>

            <x-filament-actions::modals />
        </div>
    @endvolt
</x-layouts.app>
