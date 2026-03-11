<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('database seeder creates the demo admin account', function () {
    $this->seed(DatabaseSeeder::class);

    $user = User::query()->where('email', 'admin@demo.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->username)->toBe('admin')
        ->and(Hash::check('admin', $user->password))->toBeTrue()
        ->and($user->hasRole('admin'))->toBeTrue();
});
