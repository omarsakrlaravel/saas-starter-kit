# Organization Management Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add organization management to the settings sidebar with member invitations via email, org creation, and read-only views for non-owners.

**Architecture:** Volt settings page for org management, Laravel Mailable for invites, signed URLs for invite acceptance, controller for invite handling. No new models — uses existing Organization + organization_user pivot.

**Tech Stack:** Volt (Livewire 3), Laravel Folio, Laravel Mail, signed URLs, Filament Notifications, Tailwind CSS v4

---

### Task 1: Add Organization Link to Settings Sidebar

**Files:**
- Modify: `resources/themes/anchor/components/app/settings-layout.blade.php:23-27`

**Step 1: Add the Organization section to the settings sidebar**

Insert a new "Organization" group between the Billing and Privacy groups:

```blade
<div class="px-2.5 pt-3.5 pb-1.5 text-xs lg:block hidden font-semibold leading-6 text-zinc-500">Billing</div>
<div class="flex items-center w-full ml-2 space-x-2 lg:items-stretch lg:flex-col lg:ml-0 lg:space-y-1 lg:space-x-0">
    <x-settings-sidebar-link :href="route('settings.subscription')" icon="phosphor-credit-card-duotone">Subscription</x-settings-sidebar-link>
    <x-settings-sidebar-link :href="route('settings.invoices')" icon="phosphor-invoice-duotone">Invoices</x-settings-sidebar-link>
</div>
<div class="px-2.5 pt-3.5 pb-1.5 text-xs lg:block hidden font-semibold leading-6 text-zinc-500">Organization</div>
<div class="flex items-center w-full ml-2 space-x-2 lg:items-stretch lg:flex-col lg:ml-0 lg:space-y-1 lg:space-x-0">
    <x-settings-sidebar-link :href="route('settings.organization')" icon="phosphor-buildings-duotone">Organization</x-settings-sidebar-link>
</div>
<div class="px-2.5 pt-3.5 pb-1.5 text-xs lg:block hidden font-semibold leading-6 text-zinc-500">Privacy</div>
```

Only show the Organization group if organizations are enabled (`config('wave.organizations_enabled', true)`).

**Step 2: Verify the sidebar renders without errors**

Run: `php artisan view:clear`

**Step 3: Commit**

```
feat: add organization link to settings sidebar
```

---

### Task 2: Create the Organization Settings Page (Volt)

**Files:**
- Create: `resources/themes/anchor/pages/settings/organization.blade.php`

This is the main page. It has three states:
1. **No org** — create form
2. **Owner** — full management (edit name, member list with actions, invite form)
3. **Member** — read-only view with leave button

**Step 1: Write the failing test for the organization settings page**

**Files:**
- Create: `tests/Feature/OrganizationSettingsPageTest.php`

```php
<?php

use App\Models\Organization;
use App\Models\User;

beforeEach(function () {
    $this->artisan('migrate:fresh');
    $this->seed();

    $this->user = User::factory()->create();
});

test('organization settings page loads for authenticated user', function () {
    $this->actingAs($this->user)
        ->get(route('settings.organization'))
        ->assertOk();
});

test('unauthenticated user is redirected from organization settings', function () {
    $this->get('/settings/organization')
        ->assertRedirect();
});

test('user with no organization sees create form', function () {
    $this->actingAs($this->user)
        ->get(route('settings.organization'))
        ->assertOk()
        ->assertSee('Create Organization');
});

test('owner can create organization', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('settings.organization')
        ->set('organizationName', 'My Team')
        ->call('createOrganization');

    $org = Organization::where('name', 'My Team')->first();
    expect($org)->not->toBeNull()
        ->and($org->owner_user_id)->toBe($this->user->id);
});

test('owner sees member list and invite form', function () {
    $org = Organization::create([
        'name' => 'Acme Corp',
        'slug' => 'acme-corp',
        'owner_user_id' => $this->user->id,
        'active' => true,
    ]);
    $this->user->update(['current_organization_id' => $org->id]);

    $this->actingAs($this->user)
        ->get(route('settings.organization'))
        ->assertOk()
        ->assertSee('Acme Corp')
        ->assertSee('Invite Member');
});

test('member sees read-only view without invite form', function () {
    $owner = User::factory()->create();
    $org = Organization::create([
        'name' => 'Acme Corp',
        'slug' => 'acme-corp',
        'owner_user_id' => $owner->id,
        'active' => true,
    ]);
    $this->user->organizations()->attach($org->id, [
        'role' => 'member',
        'status' => 'active',
        'joined_at' => now(),
    ]);
    $this->user->update(['current_organization_id' => $org->id]);

    $this->actingAs($this->user)
        ->get(route('settings.organization'))
        ->assertOk()
        ->assertSee('Acme Corp')
        ->assertDontSee('Invite Member');
});
```

**Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=OrganizationSettingsPage`
Expected: FAIL (route not found)

**Step 3: Create the Volt page**

Create `resources/themes/anchor/pages/settings/organization.blade.php`:

```blade
<?php

    use App\Models\Organization;
    use Livewire\Volt\Component;
    use function Laravel\Folio\{middleware, name};
    use Filament\Notifications\Notification;
    use Illuminate\Support\Str;

    middleware(['auth', 'verified']);
    name('settings.organization');

    new class extends Component
    {
        public string $organizationName = '';
        public string $editName = '';

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
            $baseSlug = $slug;
            $counter = 1;
            while (Organization::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter++;
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
            if (!$org || !$this->isOwner()) {
                return;
            }

            $org->update(['name' => $this->editName]);

            Notification::make()
                ->title('Organization name updated')
                ->success()
                ->send();
        }

        public function removeMember(int $userId): void
        {
            $org = $this->currentOrganization();
            if (!$org || !$this->isOwner()) {
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
        }

        public function revokeInvite(int $userId): void
        {
            $org = $this->currentOrganization();
            if (!$org || !$this->isOwner()) {
                return;
            }

            $org->members()->wherePivot('status', 'invited')->detach($userId);

            Notification::make()
                ->title('Invite revoked')
                ->success()
                ->send();
        }

        public function leaveOrganization(): void
        {
            $org = $this->currentOrganization();
            if (!$org || $this->isOwner()) {
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
        }

        public function switchOrganization(?int $organizationId): void
        {
            $user = auth()->user();

            if ($organizationId) {
                $isMember = $user->organizations()
                    ->where('organizations.id', $organizationId)
                    ->where('organizations.active', true)
                    ->wherePivot('status', 'active')
                    ->exists();

                if (!$isMember) {
                    return;
                }
            }

            $user->setBillingContext($organizationId);
            $user->save();

            $org = $organizationId ? Organization::find($organizationId) : null;
            $this->editName = $org?->name ?? '';

            Notification::make()
                ->title($org ? "Switched to {$org->name}" : 'Switched to personal account')
                ->success()
                ->send();

            $this->redirect(route('settings.organization'), navigate: true);
        }

        public function currentOrganization(): ?Organization
        {
            $orgId = auth()->user()->current_organization_id;
            if (!$orgId) {
                return null;
            }

            return Organization::find($orgId);
        }

        public function isOwner(): bool
        {
            $org = $this->currentOrganization();
            if (!$org) {
                return false;
            }

            return $org->owner_user_id === auth()->id();
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
                    $userOrganizations = $user->organizations()
                        ->wherePivot('status', 'active')
                        ->where('organizations.active', true)
                        ->orderBy('organizations.name')
                        ->get();
                @endphp

                {{-- Organization Switcher (only if user has orgs) --}}
                @if($userOrganizations->count() > 0)
                    <div class="p-4 mb-6 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Active Organization</h3>
                        <div class="flex flex-col gap-3 mt-3 sm:flex-row sm:items-end">
                            <select
                                wire:change="switchOrganization($event.target.value)"
                                class="w-full rounded-md border border-zinc-300 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100 text-sm"
                            >
                                <option value="">Personal Account</option>
                                @foreach($userOrganizations as $memberOrg)
                                    <option value="{{ $memberOrg->id }}" @selected($user->current_organization_id == $memberOrg->id)>
                                        {{ $memberOrg->name }}
                                        @if($memberOrg->owner_user_id === $user->id) (Owner) @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endif

                {{-- No Organization — Create Form --}}
                @if(!$org)
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

                {{-- Owner View --}}
                @elseif($isOwner)
                    {{-- Edit Name --}}
                    <div class="p-4 mb-6 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Organization Name</h3>
                        <form wire:submit="updateName" class="flex flex-col gap-3 mt-3 sm:flex-row sm:items-start">
                            <input
                                type="text"
                                wire:model="editName"
                                class="flex-1 rounded-md border border-zinc-300 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100 text-sm"
                            />
                            <x-button type="submit">Save</x-button>
                        </form>
                    </div>

                    {{-- Invite Member --}}
                    <div class="p-4 mb-6 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Invite Member</h3>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Send an email invitation to join this organization.</p>
                        <form wire:submit="inviteMember" class="flex flex-col gap-3 mt-3 sm:flex-row sm:items-start">
                            <div class="flex-1">
                                <input
                                    type="email"
                                    wire:model="inviteEmail"
                                    placeholder="colleague@example.com"
                                    class="w-full rounded-md border border-zinc-300 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100 text-sm"
                                />
                                @error('inviteEmail')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <x-button type="submit">Send Invite</x-button>
                        </form>
                    </div>

                    {{-- Members List --}}
                    <div class="bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden">
                        <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700">
                            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Members</h3>
                        </div>
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
                                    <div class="flex items-center gap-3">
                                        <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium
                                            {{ $member->pivot->role === 'owner' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300' }}">
                                            {{ ucfirst($member->pivot->role) }}
                                        </span>
                                        @if($member->pivot->status === 'invited')
                                            <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                                Pending
                                            </span>
                                            <button
                                                wire:click="revokeInvite({{ $member->id }})"
                                                wire:confirm="Revoke this invitation?"
                                                class="text-xs text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                                            >
                                                Revoke
                                            </button>
                                        @elseif($member->id !== $user->id)
                                            <button
                                                wire:click="removeMember({{ $member->id }})"
                                                wire:confirm="Remove this member from the organization?"
                                                class="text-xs text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                                            >
                                                Remove
                                            </button>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                {{-- Member View (read-only) --}}
                @else
                    <div class="p-4 mb-6 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Organization</h3>
                        <p class="mt-1 text-base text-zinc-900 dark:text-zinc-100">{{ $org->name }}</p>
                    </div>

                    {{-- Members List (read-only) --}}
                    <div class="mb-6 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden">
                        <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700">
                            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Members</h3>
                        </div>
                        <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach($org->members()->wherePivot('status', 'active')->orderByPivot('role')->get() as $member)
                                <li class="flex items-center justify-between px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <img src="{{ $member->avatar() }}" class="w-8 h-8 rounded-full" alt="">
                                        <div>
                                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $member->name }}</p>
                                            <p class="text-xs text-zinc-500">{{ $member->email }}</p>
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium
                                        {{ $member->pivot->role === 'owner' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300' }}">
                                        {{ ucfirst($member->pivot->role) }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    {{-- Leave Organization --}}
                    <div class="p-4 bg-white dark:bg-zinc-800 border border-red-200 dark:border-red-800/50 rounded-lg">
                        <h3 class="text-sm font-semibold text-red-600 dark:text-red-400">Leave Organization</h3>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">You will lose access to this organization's subscription and resources.</p>
                        <button
                            wire:click="leaveOrganization"
                            wire:confirm="Are you sure you want to leave this organization?"
                            class="mt-3 inline-flex items-center justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-500"
                        >
                            Leave Organization
                        </button>
                    </div>
                @endif

            </x-app.settings-layout>
        </div>
    @endvolt
</x-layouts.app>
```

**Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=OrganizationSettingsPage`

**Step 5: Commit**

```
feat: add organization settings page with create, manage, and member views
```

---

### Task 3: Create the Invitation Mailable

**Files:**
- Create: `app/Mail/OrganizationInvite.php`
- Create: `resources/views/mail/organization-invite.blade.php`

**Step 1: Write the failing test for the mailable**

Add to `tests/Feature/OrganizationSettingsPageTest.php`:

```php
use App\Mail\OrganizationInvite;
use App\Models\Organization;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

test('owner can invite member by email', function () {
    Mail::fake();

    $org = Organization::create([
        'name' => 'Acme Corp',
        'slug' => 'acme-corp',
        'owner_user_id' => $this->user->id,
        'active' => true,
    ]);
    $this->user->update(['current_organization_id' => $org->id]);

    Livewire\Livewire::actingAs($this->user)
        ->test('settings.organization')
        ->set('inviteEmail', 'new@example.com')
        ->call('inviteMember');

    Mail::assertSent(OrganizationInvite::class, function ($mail) {
        return $mail->hasTo('new@example.com');
    });

    expect($org->members()->wherePivot('status', 'invited')->count())->toBe(1);
});

test('member cannot send invitations', function () {
    Mail::fake();

    $owner = User::factory()->create();
    $org = Organization::create([
        'name' => 'Acme Corp',
        'slug' => 'acme-corp',
        'owner_user_id' => $owner->id,
        'active' => true,
    ]);
    $this->user->organizations()->attach($org->id, [
        'role' => 'member',
        'status' => 'active',
        'joined_at' => now(),
    ]);
    $this->user->update(['current_organization_id' => $org->id]);

    Livewire\Livewire::actingAs($this->user)
        ->test('settings.organization')
        ->set('inviteEmail', 'new@example.com')
        ->call('inviteMember');

    Mail::assertNotSent(OrganizationInvite::class);
});

test('cannot invite same email twice', function () {
    Mail::fake();

    $existingMember = User::factory()->create(['email' => 'existing@example.com']);
    $org = Organization::create([
        'name' => 'Acme Corp',
        'slug' => 'acme-corp',
        'owner_user_id' => $this->user->id,
        'active' => true,
    ]);
    $this->user->update(['current_organization_id' => $org->id]);
    $org->members()->attach($existingMember->id, [
        'role' => 'member',
        'status' => 'active',
        'joined_at' => now(),
    ]);

    Livewire\Livewire::actingAs($this->user)
        ->test('settings.organization')
        ->set('inviteEmail', 'existing@example.com')
        ->call('inviteMember')
        ->assertHasErrors('inviteEmail');

    Mail::assertNotSent(OrganizationInvite::class);
});
```

**Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=OrganizationSettingsPage`

**Step 3: Create the Mailable**

Run: `php artisan make:mail OrganizationInvite --no-interaction`

Then update `app/Mail/OrganizationInvite.php`:

```php
<?php

namespace App\Mail;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrganizationInvite extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Organization $organization,
        public string $acceptUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been invited to join {$this->organization->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.organization-invite',
        );
    }
}
```

**Step 4: Create the email view**

Create `resources/views/mail/organization-invite.blade.php`:

```blade
<x-mail::message>
# You've Been Invited

You've been invited to join **{{ $organization->name }}** on {{ config('wave.settings.site_title', config('app.name')) }}.

<x-mail::button :url="$acceptUrl">
Accept Invitation
</x-mail::button>

This invitation link will expire in 7 days. If you did not expect this invitation, you can ignore this email.

Thanks,<br>
{{ config('wave.settings.site_title', config('app.name')) }}
</x-mail::message>
```

**Step 5: Add the `inviteMember` method to the Volt component**

In `resources/themes/anchor/pages/settings/organization.blade.php`, add to the PHP class:

```php
public string $inviteEmail = '';

public function inviteMember(): void
{
    $org = $this->currentOrganization();
    if (!$org || !$this->isOwner()) {
        return;
    }

    $this->validate(['inviteEmail' => 'required|email|max:255']);

    // Check if already a member or invited
    $existingMember = $org->members()
        ->where('email', $this->inviteEmail)
        ->first();

    if ($existingMember) {
        $this->addError('inviteEmail', 'This person is already a member or has a pending invite.');
        return;
    }

    // Find or prepare user
    $invitedUser = \App\Models\User::where('email', $this->inviteEmail)->first();

    if ($invitedUser) {
        $org->members()->attach($invitedUser->id, [
            'role' => 'member',
            'status' => 'invited',
            'invited_by' => auth()->id(),
            'invited_at' => now(),
        ]);
    }

    // Generate signed URL
    $acceptUrl = \Illuminate\Support\Facades\URL::signedRoute(
        'organization.invite.accept',
        [
            'organization' => $org->id,
            'email' => $this->inviteEmail,
        ],
        now()->addDays(7)
    );

    \Illuminate\Support\Facades\Mail::to($this->inviteEmail)
        ->send(new \App\Mail\OrganizationInvite($org, $acceptUrl));

    $this->inviteEmail = '';

    Notification::make()
        ->title('Invitation sent')
        ->success()
        ->send();
}
```

**Step 6: Run tests to verify they pass**

Run: `php artisan test --compact --filter=OrganizationSettingsPage`

**Step 7: Commit**

```
feat: add organization invite mailable and inviteMember action
```

---

### Task 4: Create Invite Acceptance Controller and Route

**Files:**
- Create: `wave/src/Http/Controllers/OrganizationInviteController.php`
- Modify: `wave/routes/web.php`

**Step 1: Write the failing test for invite acceptance**

Add to `tests/Feature/OrganizationSettingsPageTest.php`:

```php
test('existing user can accept invite via signed URL', function () {
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $org = Organization::create([
        'name' => 'Acme Corp',
        'slug' => 'acme-corp',
        'owner_user_id' => $this->user->id,
        'active' => true,
    ]);
    $org->members()->attach($invitedUser->id, [
        'role' => 'member',
        'status' => 'invited',
        'invited_by' => $this->user->id,
        'invited_at' => now(),
    ]);

    $url = URL::signedRoute('organization.invite.accept', [
        'organization' => $org->id,
        'email' => 'invited@example.com',
    ], now()->addDays(7));

    $this->actingAs($invitedUser)
        ->get($url)
        ->assertRedirect('/dashboard');

    $membership = $org->members()->where('users.id', $invitedUser->id)->first();
    expect($membership->pivot->status)->toBe('active')
        ->and($membership->pivot->joined_at)->not->toBeNull();
});

test('invalid signed URL is rejected', function () {
    $org = Organization::create([
        'name' => 'Acme Corp',
        'slug' => 'acme-corp',
        'owner_user_id' => $this->user->id,
        'active' => true,
    ]);

    $this->get(route('organization.invite.accept', [
        'organization' => $org->id,
        'email' => 'test@example.com',
    ]))
        ->assertForbidden();
});

test('guest clicking invite is redirected to login with invite in session', function () {
    $org = Organization::create([
        'name' => 'Acme Corp',
        'slug' => 'acme-corp',
        'owner_user_id' => $this->user->id,
        'active' => true,
    ]);

    $url = URL::signedRoute('organization.invite.accept', [
        'organization' => $org->id,
        'email' => 'newuser@example.com',
    ], now()->addDays(7));

    $this->get($url)
        ->assertRedirect()
        ->assertSessionHas('org_invite_url');
});
```

**Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=OrganizationSettingsPage`

**Step 3: Create the controller**

Create `wave/src/Http/Controllers/OrganizationInviteController.php`:

```php
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

        if (! auth()->check()) {
            session(['org_invite_url' => $request->fullUrl()]);

            return redirect()->route('login');
        }

        $user = auth()->user();

        // Verify the authenticated user's email matches the invite
        if ($user->email !== $email) {
            // Check if this email belongs to an existing user
            $invitedUser = User::where('email', $email)->first();
            if ($invitedUser && $invitedUser->id !== $user->id) {
                return redirect('/dashboard')->with([
                    'message' => 'This invitation was sent to a different email address.',
                    'message_type' => 'danger',
                ]);
            }
        }

        // Accept the invite: update existing pivot or create new one
        $existingMembership = $organization->members()
            ->where('users.id', $user->id)
            ->first();

        if ($existingMembership) {
            if ($existingMembership->pivot->status === 'active') {
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
```

**Step 4: Add route to web.php**

In `wave/routes/web.php`, add inside the auth group:

```php
Route::get('organization/invite/{organization}/accept', '\Wave\Http\Controllers\OrganizationInviteController@accept')
    ->name('organization.invite.accept');
```

Also add a guest-accessible version outside the auth group (before the auth group):

```php
Route::get('organization/invite/{organization}/accept', '\Wave\Http\Controllers\OrganizationInviteController@accept')
    ->name('organization.invite.accept');
```

Actually, this route should be accessible by both guests and authenticated users. Place it **outside** the auth group so guests can hit it (the controller handles the redirect to login for guests).

**Step 5: Run tests to verify they pass**

Run: `php artisan test --compact --filter=OrganizationSettingsPage`

**Step 6: Commit**

```
feat: add invite acceptance controller with signed URL validation
```

---

### Task 5: Handle Post-Login Invite Acceptance

**Files:**
- Modify: `wave/src/WaveServiceProvider.php` (or create a listener)

When a guest clicks an invite link, they're redirected to login with `org_invite_url` in session. After login, they should be redirected to that URL to complete the acceptance.

**Step 1: Write the failing test**

Add to `tests/Feature/OrganizationSettingsPageTest.php`:

```php
test('after login, user is redirected to pending invite URL from session', function () {
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $org = Organization::create([
        'name' => 'Acme Corp',
        'slug' => 'acme-corp',
        'owner_user_id' => $this->user->id,
        'active' => true,
    ]);

    $org->members()->attach($invitedUser->id, [
        'role' => 'member',
        'status' => 'invited',
        'invited_by' => $this->user->id,
        'invited_at' => now(),
    ]);

    $url = URL::signedRoute('organization.invite.accept', [
        'organization' => $org->id,
        'email' => 'invited@example.com',
    ], now()->addDays(7));

    // Simulate: guest hit invite URL, got redirected, now logging in with the session
    $this->actingAs($invitedUser)
        ->withSession(['org_invite_url' => $url])
        ->get('/dashboard')
        ->assertRedirect($url);

    // Clear session after redirect
    expect(session('org_invite_url'))->toBeNull();
});
```

**Step 2: Implement the redirect middleware**

Create `wave/src/Http/Middleware/HandleOrganizationInvite.php`:

```php
<?php

namespace Wave\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class HandleOrganizationInvite
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check() && session()->has('org_invite_url')) {
            $url = session()->pull('org_invite_url');

            return redirect($url);
        }

        return $next($request);
    }
}
```

Register in `wave/src/WaveServiceProvider.php`:

```php
$this->app->router->aliasMiddleware('handle-org-invite', HandleOrganizationInvite::class);
```

Apply it to the auth group in `bootstrap/app.php` or add it to the web middleware group. Alternatively, apply it only to the dashboard route. The simplest approach: register it as a web middleware that runs on every authenticated request.

**Step 3: Run tests**

Run: `php artisan test --compact --filter=OrganizationSettingsPage`

**Step 4: Commit**

```
feat: add post-login redirect for pending organization invites
```

---

### Task 6: Add Auth Routes Dataset Entry and Final Integration Tests

**Files:**
- Modify: `tests/Datasets/AuthRoutes.php`

**Step 1: Add the new route to the auth routes dataset**

Add `'/settings/organization'` to the dataset array.

**Step 2: Run the full test suite to ensure nothing is broken**

Run: `php artisan test --compact`

**Step 3: Run pint**

Run: `vendor/bin/pint --dirty --format agent`

**Step 4: Commit**

```
feat: add organization settings route to test datasets
```

---

### Task 7: Final Review and Cleanup

**Step 1: Manual verification checklist**

1. Visit `/settings/organization` — should see create form if no org
2. Create an org — should auto-switch billing context
3. Check member list shows you as owner
4. Invite an email — check mailable is sent
5. Switch to another browser/incognito — click invite link as guest — should redirect to login
6. Login and verify auto-redirect to acceptance
7. Switch org in dropdown — verify context changes
8. As member — verify read-only view, no invite form
9. Leave organization — verify detached and redirected

**Step 2: Run full test suite**

Run: `php artisan test --compact`

**Step 3: Commit any final fixes**

```
chore: organization management final polish
```
