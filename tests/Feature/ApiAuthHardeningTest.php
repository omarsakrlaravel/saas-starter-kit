<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rejects invalid api registration payloads', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Test User',
        'username' => 'test-user',
        'email' => 'not-an-email',
        'password' => 'short',
        'password_confirmation' => 'different',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);

    expect(User::query()->where('username', 'test-user')->exists())->toBeFalse();
});

it('uses the configured minimum api password length', function () {
    config(['saas.auth.min_password_length' => 12]);

    $response = $this->postJson('/api/register', [
        'name' => 'Test User',
        'username' => 'test-user',
        'email' => 'test@example.com',
        'password' => 'short-pass',
        'password_confirmation' => 'short-pass',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);

    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
});

it('registers a user when the api payload is valid', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Test User',
        'username' => 'test-user',
        'email' => 'test@example.com',
        'password' => 'super-secure-password',
        'password_confirmation' => 'super-secure-password',
    ]);

    $response
        ->assertSuccessful()
        ->assertJsonStructure(['access_token', 'token_type', 'expires_in']);

    expect(User::query()->where('email', 'test@example.com')->exists())->toBeTrue();
});

it('logs out via post and invalidates the session', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $oldToken = session()->token();

    $response = $this->post('/logout');

    $response->assertRedirect(route('login'));
    $this->assertGuest();
    expect(session()->token())->not()->toBe($oldToken);
});

it('does not allow get requests to the logout endpoint', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/logout')
        ->assertNotFound();
});
