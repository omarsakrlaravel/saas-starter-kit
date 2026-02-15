<?php

use App\Filament\Resources\Coupons\CouponResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Organizations\OrganizationResource;
use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use App\Filament\Resources\Plans\PlanResource;
use App\Filament\Resources\Plans\RelationManagers\SubscriptionsRelationManager as PlanSubscriptionsRM;
use App\Filament\Resources\PromotionCodes\PromotionCodeResource;
use App\Filament\Resources\Refunds\RefundResource;
use App\Filament\Resources\Subscriptions\RelationManagers\InvoicesRelationManager as SubInvoicesRM;
use App\Filament\Resources\Subscriptions\RelationManagers\TransactionsRelationManager as SubTransactionsRM;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Filament\Resources\Users\RelationManagers\ApiKeysRelationManager;
use App\Filament\Resources\Users\UserResource;

// --- Navigation Group Tests ---

test('PlanResource belongs to Billing navigation group', function () {
    $reflection = new ReflectionClass(PlanResource::class);
    $property = $reflection->getProperty('navigationGroup');
    $property->setAccessible(true);

    expect($property->getValue())->toBe('Billing');
});

test('SubscriptionResource belongs to Billing navigation group', function () {
    $reflection = new ReflectionClass(SubscriptionResource::class);
    $property = $reflection->getProperty('navigationGroup');
    $property->setAccessible(true);

    expect($property->getValue())->toBe('Billing');
});

test('InvoiceResource belongs to Billing navigation group', function () {
    $reflection = new ReflectionClass(InvoiceResource::class);
    $property = $reflection->getProperty('navigationGroup');
    $property->setAccessible(true);

    expect($property->getValue())->toBe('Billing');
});

test('TransactionResource belongs to Billing navigation group', function () {
    $reflection = new ReflectionClass(TransactionResource::class);
    $property = $reflection->getProperty('navigationGroup');
    $property->setAccessible(true);

    expect($property->getValue())->toBe('Billing');
});

test('CouponResource belongs to Billing navigation group', function () {
    $reflection = new ReflectionClass(CouponResource::class);
    $property = $reflection->getProperty('navigationGroup');
    $property->setAccessible(true);

    expect($property->getValue())->toBe('Billing');
});

test('PromotionCodeResource belongs to Billing navigation group', function () {
    $reflection = new ReflectionClass(PromotionCodeResource::class);
    $property = $reflection->getProperty('navigationGroup');
    $property->setAccessible(true);

    expect($property->getValue())->toBe('Billing');
});

test('PaymentMethodResource belongs to Billing navigation group', function () {
    $reflection = new ReflectionClass(PaymentMethodResource::class);
    $property = $reflection->getProperty('navigationGroup');
    $property->setAccessible(true);

    expect($property->getValue())->toBe('Billing');
});

test('RefundResource belongs to Billing navigation group', function () {
    $reflection = new ReflectionClass(RefundResource::class);
    $property = $reflection->getProperty('navigationGroup');
    $property->setAccessible(true);

    expect($property->getValue())->toBe('Billing');
});

test('UserResource belongs to People navigation group', function () {
    $reflection = new ReflectionClass(UserResource::class);
    $property = $reflection->getProperty('navigationGroup');
    $property->setAccessible(true);

    expect($property->getValue())->toBe('People');
});

test('OrganizationResource belongs to People navigation group', function () {
    $reflection = new ReflectionClass(OrganizationResource::class);
    $property = $reflection->getProperty('navigationGroup');
    $property->setAccessible(true);

    expect($property->getValue())->toBe('People');
});

// --- Relation Manager Registration Tests ---

test('PlanResource registers SubscriptionsRelationManager', function () {
    $relations = PlanResource::getRelations();

    expect($relations)->toContain(PlanSubscriptionsRM::class);
});

test('SubscriptionResource registers InvoicesRelationManager', function () {
    $relations = SubscriptionResource::getRelations();

    expect($relations)->toContain(SubInvoicesRM::class);
});

test('SubscriptionResource registers TransactionsRelationManager', function () {
    $relations = SubscriptionResource::getRelations();

    expect($relations)->toContain(SubTransactionsRM::class);
});

test('UserResource registers ApiKeysRelationManager', function () {
    $relations = UserResource::getRelations();

    expect($relations)->toContain(ApiKeysRelationManager::class);
});

// --- Relationship Wiring Tests ---

test('SubscriptionsRelationManager uses subscriptions relationship', function () {
    $reflection = new ReflectionClass(PlanSubscriptionsRM::class);
    $property = $reflection->getProperty('relationship');
    $property->setAccessible(true);

    expect($property->getValue())->toBe('subscriptions');
});

test('InvoicesRelationManager uses localInvoices relationship', function () {
    $reflection = new ReflectionClass(SubInvoicesRM::class);
    $property = $reflection->getProperty('relationship');
    $property->setAccessible(true);

    expect($property->getValue())->toBe('localInvoices');
});

test('TransactionsRelationManager uses transactions relationship', function () {
    $reflection = new ReflectionClass(SubTransactionsRM::class);
    $property = $reflection->getProperty('relationship');
    $property->setAccessible(true);

    expect($property->getValue())->toBe('transactions');
});

test('ApiKeysRelationManager uses apiKeys relationship', function () {
    $reflection = new ReflectionClass(ApiKeysRelationManager::class);
    $property = $reflection->getProperty('relationship');
    $property->setAccessible(true);

    expect($property->getValue())->toBe('apiKeys');
});

// --- Model Relationship Method Tests ---

test('Subscription model has localInvoices relationship method', function () {
    expect(method_exists(\Wave\Subscription::class, 'localInvoices'))->toBeTrue();
});

test('Subscription model has transactions relationship method', function () {
    expect(method_exists(\Wave\Subscription::class, 'transactions'))->toBeTrue();
});

test('Plan model has subscriptions relationship method', function () {
    expect(method_exists(\Wave\Plan::class, 'subscriptions'))->toBeTrue();
});

// --- PaymentMethodResource is read-only ---

test('PaymentMethodResource does not allow creation', function () {
    expect(PaymentMethodResource::canCreate())->toBeFalse();
});
