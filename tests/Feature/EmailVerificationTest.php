<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

it('redirects unverified users to verification notice', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect(route('verification.notice'));
});

it('allows verified users to access protected pages', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertSuccessful();
});

it('allows unverified users to see the verification notice page', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertSuccessful();
});

it('redirects unverified users from settings pages', function (string $url) {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get($url)
        ->assertRedirect(route('verification.notice'));
})->with([
    '/settings/profile',
    '/settings/security',
    '/settings/api',
    '/settings/subscription',
    '/settings/invoices',
    '/settings/notifications',
    '/settings/social',
    '/settings/privacy',
    '/settings/export',
    '/settings/deletion',
    '/settings/sessions',
    '/settings/activity',
]);

it('redirects verified users away from verification notice page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertRedirect('/');
});

it('sends verification notification to unverified user', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $user->sendEmailVerificationNotification();

    Notification::assertSentTo($user, VerifyEmail::class);
});
