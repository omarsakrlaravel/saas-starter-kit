<?php

/**
 * UserResource Test Suite
 *
 * Tests the enhanced UserResource including:
 * - Navigation group configuration
 * - Relation manager registration and relationship targets
 * - User model invoices() morph relationship definition
 */

use App\Filament\Resources\Users\RelationManagers\ActivityRelationManager;
use App\Filament\Resources\Users\RelationManagers\ApiKeysRelationManager;
use App\Filament\Resources\Users\RelationManagers\InvoicesRelationManager;
use App\Filament\Resources\Users\RelationManagers\OrganizationsRelationManager;
use App\Filament\Resources\Users\RelationManagers\StatusHistoryRelationManager;
use App\Filament\Resources\Users\RelationManagers\SubscriptionsRelationManager;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;

test('user resource has People navigation group', function () {
    expect(UserResource::getNavigationGroup())->toBe('People');
});

test('user resource registers all relation managers', function () {
    $relations = UserResource::getRelations();

    expect($relations)->toHaveCount(6)
        ->toContain(SubscriptionsRelationManager::class)
        ->toContain(InvoicesRelationManager::class)
        ->toContain(OrganizationsRelationManager::class)
        ->toContain(ActivityRelationManager::class)
        ->toContain(ApiKeysRelationManager::class)
        ->toContain(StatusHistoryRelationManager::class);
});

test('subscriptions relation manager targets subscriptions relationship', function () {
    $reflection = new ReflectionClass(SubscriptionsRelationManager::class);
    $property = $reflection->getProperty('relationship');

    expect($property->getDefaultValue())->toBe('subscriptions');
});

test('invoices relation manager targets localInvoices relationship', function () {
    $reflection = new ReflectionClass(InvoicesRelationManager::class);
    $property = $reflection->getProperty('relationship');

    expect($property->getDefaultValue())->toBe('localInvoices');
});

test('organizations relation manager targets organizations relationship', function () {
    $reflection = new ReflectionClass(OrganizationsRelationManager::class);
    $property = $reflection->getProperty('relationship');

    expect($property->getDefaultValue())->toBe('organizations');
});

test('activity relation manager targets activityLogs relationship', function () {
    $reflection = new ReflectionClass(ActivityRelationManager::class);
    $property = $reflection->getProperty('relationship');

    expect($property->getDefaultValue())->toBe('activityLogs');
});

test('activity relation manager has custom title', function () {
    $reflection = new ReflectionClass(ActivityRelationManager::class);
    $property = $reflection->getProperty('title');

    expect($property->getDefaultValue())->toBe('Activity');
});

test('user model defines localInvoices method', function () {
    expect(method_exists(User::class, 'localInvoices'))->toBeTrue();

    $reflection = new ReflectionMethod(User::class, 'localInvoices');

    expect($reflection->getReturnType()?->getName())
        ->toBe('Illuminate\Database\Eloquent\Relations\MorphMany');
});
