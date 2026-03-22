<?php

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);
});

afterEach(function () {
    $this->user->forceFill([
        'two_factor_secret' => null,
        'two_factor_recovery_codes' => null,
        'two_factor_confirmed_at' => null,
    ])->save();
});

it('security page requires authentication', function () {
    $response = $this->get(route('settings.security'));

    $response->assertRedirect(route('login'));
});

it('authenticated user can access security page', function () {
    $this->actingAs($this->user);

    $response = $this->get(route('settings.security'));

    $response->assertOk();
    $response->assertSee('Two-Factor Authentication');
});

it('shows enable button when 2FA is not enabled', function () {
    $this->actingAs($this->user);

    $response = $this->get(route('settings.security'));

    $response->assertOk();
    $response->assertSee('Enable Two-Factor Authentication');
});

it('can enable 2FA with correct password', function () {
    $this->actingAs($this->user);

    Volt::test('settings.security')
        ->set('twoFactorPassword', 'password')
        ->call('enableTwoFactor')
        ->assertHasNoErrors()
        ->assertSet('twoFactorEnabled', true);

    $this->user->refresh();
    expect($this->user->two_factor_secret)->not->toBeNull();
    expect($this->user->two_factor_recovery_codes)->not->toBeNull();
});

it('cannot enable 2FA with wrong password', function () {
    $this->actingAs($this->user);

    Volt::test('settings.security')
        ->set('twoFactorPassword', 'wrong-password')
        ->call('enableTwoFactor')
        ->assertHasErrors('twoFactorPassword');
});

it('can confirm 2FA with valid OTP code', function () {
    $this->actingAs($this->user);

    $component = Volt::test('settings.security')
        ->set('twoFactorPassword', 'password')
        ->call('enableTwoFactor');

    $setupKey = $component->get('setupKey');

    $google2fa = new Google2FA();
    $validCode = $google2fa->getCurrentOtp($setupKey);

    $component
        ->set('confirmCode', $validCode)
        ->call('confirmTwoFactor')
        ->assertHasNoErrors()
        ->assertSet('twoFactorConfirmed', true);

    $this->user->refresh();
    expect($this->user->two_factor_confirmed_at)->not->toBeNull();
});

it('cannot confirm 2FA with invalid OTP code', function () {
    $this->actingAs($this->user);

    $component = Volt::test('settings.security')
        ->set('twoFactorPassword', 'password')
        ->call('enableTwoFactor');

    $component
        ->set('confirmCode', '000000')
        ->call('confirmTwoFactor')
        ->assertHasErrors('confirmCode');

    $this->user->refresh();
    expect($this->user->two_factor_confirmed_at)->toBeNull();
});

it('can cancel 2FA setup before confirming', function () {
    $this->actingAs($this->user);

    Volt::test('settings.security')
        ->set('twoFactorPassword', 'password')
        ->call('enableTwoFactor')
        ->assertSet('twoFactorEnabled', true)
        ->call('cancelTwoFactorSetup')
        ->assertSet('twoFactorEnabled', false);

    $this->user->refresh();
    expect($this->user->two_factor_secret)->toBeNull();
});

it('can disable 2FA with correct password', function () {
    $this->actingAs($this->user);

    $component = Volt::test('settings.security')
        ->set('twoFactorPassword', 'password')
        ->call('enableTwoFactor');

    $setupKey = $component->get('setupKey');
    $google2fa = new Google2FA();
    $validCode = $google2fa->getCurrentOtp($setupKey);

    $component
        ->set('confirmCode', $validCode)
        ->call('confirmTwoFactor')
        ->assertSet('twoFactorConfirmed', true);

    $component
        ->set('twoFactorPassword', 'password')
        ->call('disableTwoFactor')
        ->assertHasNoErrors()
        ->assertSet('twoFactorConfirmed', false)
        ->assertSet('twoFactorEnabled', false);

    $this->user->refresh();
    expect($this->user->two_factor_secret)->toBeNull();
    expect($this->user->two_factor_confirmed_at)->toBeNull();
});

it('cannot disable 2FA with wrong password', function () {
    $this->actingAs($this->user);

    $component = Volt::test('settings.security')
        ->set('twoFactorPassword', 'password')
        ->call('enableTwoFactor');

    $setupKey = $component->get('setupKey');
    $google2fa = new Google2FA();
    $validCode = $google2fa->getCurrentOtp($setupKey);

    $component
        ->set('confirmCode', $validCode)
        ->call('confirmTwoFactor');

    $component
        ->set('twoFactorPassword', 'wrong-password')
        ->call('disableTwoFactor')
        ->assertHasErrors('twoFactorPassword');

    $this->user->refresh();
    expect($this->user->two_factor_confirmed_at)->not->toBeNull();
});

it('can show recovery codes with correct password', function () {
    $this->actingAs($this->user);

    $component = Volt::test('settings.security')
        ->set('twoFactorPassword', 'password')
        ->call('enableTwoFactor');

    $setupKey = $component->get('setupKey');
    $google2fa = new Google2FA();
    $validCode = $google2fa->getCurrentOtp($setupKey);

    $component
        ->set('confirmCode', $validCode)
        ->call('confirmTwoFactor');

    $component
        ->set('twoFactorPassword', 'password')
        ->call('showRecoveryCodes')
        ->assertHasNoErrors()
        ->assertSet('showingRecoveryCodes', true);

    $codes = $component->get('recoveryCodes');
    expect($codes)->toBeArray()->toHaveCount(8);
});

it('can regenerate recovery codes', function () {
    $this->actingAs($this->user);

    $component = Volt::test('settings.security')
        ->set('twoFactorPassword', 'password')
        ->call('enableTwoFactor');

    $setupKey = $component->get('setupKey');
    $google2fa = new Google2FA();
    $validCode = $google2fa->getCurrentOtp($setupKey);

    $component
        ->set('confirmCode', $validCode)
        ->call('confirmTwoFactor');

    $originalCodes = json_decode(decrypt($this->user->fresh()->two_factor_recovery_codes), true);

    $component
        ->set('twoFactorPassword', 'password')
        ->call('regenerateRecoveryCodes')
        ->assertHasNoErrors()
        ->assertSet('showingRecoveryCodes', true);

    $newCodes = json_decode(decrypt($this->user->fresh()->two_factor_recovery_codes), true);
    expect($newCodes)->not->toBe($originalCodes);
});

it('logs activity when 2FA is enabled', function () {
    $this->actingAs($this->user);

    $component = Volt::test('settings.security')
        ->set('twoFactorPassword', 'password')
        ->call('enableTwoFactor');

    $setupKey = $component->get('setupKey');
    $google2fa = new Google2FA();
    $validCode = $google2fa->getCurrentOtp($setupKey);

    $component
        ->set('confirmCode', $validCode)
        ->call('confirmTwoFactor');

    expect(ActivityLog::where('user_id', $this->user->id)
        ->where('action', 'two_factor_enabled')
        ->exists())->toBeTrue();
});

it('logs activity when 2FA is disabled', function () {
    $this->actingAs($this->user);

    $component = Volt::test('settings.security')
        ->set('twoFactorPassword', 'password')
        ->call('enableTwoFactor');

    $setupKey = $component->get('setupKey');
    $google2fa = new Google2FA();
    $validCode = $google2fa->getCurrentOtp($setupKey);

    $component
        ->set('confirmCode', $validCode)
        ->call('confirmTwoFactor');

    $component
        ->set('twoFactorPassword', 'password')
        ->call('disableTwoFactor');

    expect(ActivityLog::where('user_id', $this->user->id)
        ->where('action', 'two_factor_disabled')
        ->exists())->toBeTrue();
});
