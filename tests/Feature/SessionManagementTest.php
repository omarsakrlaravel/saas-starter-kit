<?php

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);
});

it('sessions page requires authentication', function () {
    $response = $this->get(route('settings.sessions'));

    $response->assertRedirect(route('login'));
});

it('authenticated user can access sessions page', function () {
    $this->actingAs($this->user);

    $response = $this->get(route('settings.sessions'));

    $response->assertOk();
    $response->assertSee('Browser Sessions');
});

it('shows database driver notice when not using database sessions', function () {
    config(['session.driver' => 'file']);

    $this->actingAs($this->user);

    $response = $this->get(route('settings.sessions'));

    $response->assertOk();
    $response->assertSee('SESSION_DRIVER');
});

it('can log out other sessions with correct password', function () {
    config(['session.driver' => 'database']);

    $this->actingAs($this->user);

    $currentSessionId = session()->getId();

    DB::table('sessions')->insert([
        [
            'id' => 'other-session-1',
            'user_id' => $this->user->id,
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/120.0.0.0',
            'payload' => base64_encode('test'),
            'last_activity' => now()->timestamp,
        ],
        [
            'id' => 'other-session-2',
            'user_id' => $this->user->id,
            'ip_address' => '10.0.0.1',
            'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Safari/605.1.15',
            'payload' => base64_encode('test'),
            'last_activity' => now()->subHour()->timestamp,
        ],
    ]);

    Volt::test('settings.sessions')
        ->set('password', 'password')
        ->call('logoutOtherSessions')
        ->assertHasNoErrors();

    expect(DB::table('sessions')
        ->where('user_id', $this->user->id)
        ->where('id', '!=', $currentSessionId)
        ->count())->toBe(0);
});

it('cannot log out other sessions with wrong password', function () {
    config(['session.driver' => 'database']);

    $this->actingAs($this->user);

    Volt::test('settings.sessions')
        ->set('password', 'wrong-password')
        ->call('logoutOtherSessions')
        ->assertHasErrors('password');
});

it('logs activity when other sessions are logged out', function () {
    config(['session.driver' => 'database']);

    $this->actingAs($this->user);

    Volt::test('settings.sessions')
        ->set('password', 'password')
        ->call('logoutOtherSessions');

    expect(ActivityLog::where('user_id', $this->user->id)
        ->where('action', 'sessions_logged_out')
        ->exists())->toBeTrue();
});
