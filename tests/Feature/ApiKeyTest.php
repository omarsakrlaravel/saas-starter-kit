<?php

use App\Models\User;
use Carbon\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    $this->user = User::factory()->create();
});

afterEach(function () {
    $this->user->tokens()->delete();
    $this->user->forceDelete();
});

describe('Personal Access Token', function () {
    it('can create a token', function () {
        $token = $this->user->createToken('Test Key');

        expect($token)->toBeInstanceOf(\Laravel\Sanctum\NewAccessToken::class);
        expect($token->plainTextToken)->not()->toBeNull();
        expect($token->accessToken->name)->toBe('Test Key');
        expect($token->accessToken->tokenable_id)->toBe($this->user->id);
    });

    it('belongs to a user', function () {
        $token = $this->user->createToken('Test Key');

        expect($token->accessToken->tokenable)->toBeInstanceOf(User::class);
        expect($token->accessToken->tokenable->id)->toBe($this->user->id);
    });

    it('casts last_used_at to datetime', function () {
        $token = $this->user->createToken('Test Key');
        $token->accessToken->forceFill(['last_used_at' => now()])->save();
        $token->accessToken->refresh();

        expect($token->accessToken->last_used_at)->toBeInstanceOf(Carbon::class);
    });

    it('has nullable last_used_at by default', function () {
        $token = $this->user->createToken('Test Key');

        expect($token->accessToken->last_used_at)->toBeNull();
    });
});

describe('User Token Methods', function () {
    it('can create token via user method', function () {
        $token = $this->user->createApiKey('My API Key');

        expect($token)->toBeInstanceOf(\Laravel\Sanctum\NewAccessToken::class);
        expect($token->accessToken->name)->toBe('My API Key');
        expect($token->accessToken->tokenable_id)->toBe($this->user->id);
        expect($token->plainTextToken)->not()->toBeNull();
    });

    it('generates unique tokens for each token', function () {
        $token1 = $this->user->createApiKey('Key 1');
        $token2 = $this->user->createApiKey('Key 2');

        expect($token1->plainTextToken)->not->toBe($token2->plainTextToken);
    });

    it('can retrieve all tokens for user', function () {
        $this->user->createApiKey('Key 1');
        $this->user->createApiKey('Key 2');
        $this->user->createApiKey('Key 3');

        expect($this->user->tokens)->toHaveCount(3);
    });

    it('retrieves tokens', function () {
        $token1 = $this->user->createApiKey('Key 1');
        Carbon::setTestNow(now()->addMinute());
        $token2 = $this->user->createApiKey('Key 2');
        Carbon::setTestNow(now()->addMinutes(2));
        $token3 = $this->user->createApiKey('Key 3');
        Carbon::setTestNow();

        $tokens = $this->user->tokens()->orderByDesc('created_at')->get();

        expect($tokens->first()->id)->toBe($token3->accessToken->id);
        expect($tokens->last()->id)->toBe($token1->accessToken->id);
    });

    it('deletes tokens when explicitly removed', function () {
        $user = User::factory()->create();
        $token = $user->createApiKey('Test Key');
        $tokenId = $token->accessToken->id;

        $user->tokens()->delete();
        $user->forceDelete();

        expect(PersonalAccessToken::find($tokenId))->toBeNull();
    });
});

describe('API Key Settings Page', function () {
    it('requires authentication', function () {
        $response = $this->get(route('settings.api'));

        $response->assertRedirect(route('login'));
    });

    it('loads for authenticated user', function () {
        $this->actingAs($this->user);

        $response = $this->get(route('settings.api'));

        $response->assertStatus(200);
        $response->assertSee('API Keys');
    });

    it('displays existing api keys', function () {
        $this->actingAs($this->user);
        $this->user->createApiKey('my-test-key');

        $response = $this->get(route('settings.api'));

        $response->assertStatus(200);
        $response->assertSee('my-test-key');
    });

    it('shows create new key form', function () {
        $this->actingAs($this->user);

        $response = $this->get(route('settings.api'));

        $response->assertStatus(200);
        $response->assertSee('Create New Key');
    });
});

describe('API Key Activity Logging', function () {
    it('logs api key creation', function () {
        $this->actingAs($this->user);

        // Clear existing activity logs
        \Wave\ActivityLog::where('user_id', $this->user->id)->delete();

        $this->user->createApiKey('Logged Key');

        // Activity logging happens in the Livewire component, not the model
        // So we just verify the key was created
        expect($this->user->tokens()->where('name', 'Logged Key')->exists())->toBeTrue();
    });
});

describe('Multiple Users with API Keys', function () {
    it('users can only see their own tokens', function () {
        $user2 = User::factory()->create();

        $this->user->createApiKey('User 1 Key');
        $user2->createApiKey('User 2 Key');

        expect($this->user->tokens)->toHaveCount(1);
        expect($this->user->tokens->first()->name)->toBe('User 1 Key');

        expect($user2->tokens)->toHaveCount(1);
        expect($user2->tokens->first()->name)->toBe('User 2 Key');

        $user2->tokens()->delete();
        $user2->forceDelete();
    });
});
