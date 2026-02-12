<?php

use Devdojo\Auth\Helper;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;

beforeEach(function () {
    config()->set('devdojo.auth.providers.google.client_id', 'fake-google-id');
    config()->set('devdojo.auth.providers.google.client_secret', 'fake-google-secret');
    config()->set('devdojo.auth.providers.github.client_id', 'fake-github-id');
    config()->set('devdojo.auth.providers.github.client_secret', 'fake-github-secret');
});

it('lists google and github as active providers', function () {
    $active = Helper::activeProviders();

    expect($active)->toHaveKeys(['google', 'github']);
});

it('does not list inactive providers', function () {
    $active = Helper::activeProviders();

    expect($active)->not->toHaveKey('facebook')
        ->not->toHaveKey('twitter');
});

it('redirects to google oauth', function () {
    $mockProvider = Mockery::mock(AbstractProvider::class);
    $mockProvider->shouldReceive('redirect')
        ->once()
        ->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));

    Socialite::shouldReceive('driver')
        ->with('google')
        ->once()
        ->andReturn($mockProvider);

    $this->get('auth/google/redirect')
        ->assertRedirect()
        ->assertRedirectContains('accounts.google.com');
});

it('redirects to github oauth', function () {
    $mockProvider = Mockery::mock(AbstractProvider::class);
    $mockProvider->shouldReceive('redirect')
        ->once()
        ->andReturn(redirect('https://github.com/login/oauth/authorize'));

    Socialite::shouldReceive('driver')
        ->with('github')
        ->once()
        ->andReturn($mockProvider);

    $this->get('auth/github/redirect')
        ->assertRedirect()
        ->assertRedirectContains('github.com');
});

it('does not include inactive providers in active list', function () {
    config()->set('devdojo.auth.providers.facebook.active', false);

    $active = Helper::activeProviders();

    expect($active)->not->toHaveKey('facebook');
});
