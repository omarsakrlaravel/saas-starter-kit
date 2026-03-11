<?php

use App\Enums\FileAccessLevel;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Wave\File;

uses(RefreshDatabase::class);

it('creates a file with auto-generated uuid', function () {
    $user = User::factory()->create();
    $file = File::factory()->forUser($user)->create();

    expect($file->uuid)->not()->toBeNull()
        ->and($file->uuid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
});

it('does not overwrite a pre-set uuid', function () {
    $user = User::factory()->create();
    $customUuid = 'custom-uuid-value';
    $file = File::factory()->forUser($user)->create(['uuid' => $customUuid]);

    expect($file->uuid)->toBe($customUuid);
});

it('casts access_level to FileAccessLevel enum', function () {
    $user = User::factory()->create();
    $privateFile = File::factory()->forUser($user)->create();
    $publicFile = File::factory()->appPublic()->forUser($user)->create();

    expect($privateFile->access_level)->toBeInstanceOf(FileAccessLevel::class)
        ->and($privateFile->access_level)->toBe(FileAccessLevel::Private)
        ->and($publicFile->access_level)->toBe(FileAccessLevel::AppPublic);
});

it('casts metadata to array and size_bytes to integer', function () {
    $user = User::factory()->create();
    $file = File::factory()->forUser($user)->create([
        'metadata' => ['key' => 'value'],
        'size_bytes' => '12345',
    ]);

    expect($file->metadata)->toBeArray()
        ->and($file->metadata)->toBe(['key' => 'value'])
        ->and($file->size_bytes)->toBeInt()
        ->and($file->size_bytes)->toBe(12345);
});

it('has an uploadedBy relationship to user', function () {
    $user = User::factory()->create();
    $file = File::factory()->forUser($user)->create();

    expect($file->uploadedBy)->toBeInstanceOf(User::class)
        ->and($file->uploadedBy->id)->toBe($user->id);
});

it('returns correct values for access level helpers', function () {
    $user = User::factory()->create();
    $privateFile = File::factory()->forUser($user)->create();
    $publicFile = File::factory()->appPublic()->forUser($user)->create();

    expect($privateFile->isPrivate())->toBeTrue()
        ->and($privateFile->isAppPublic())->toBeFalse()
        ->and($publicFile->isPrivate())->toBeFalse()
        ->and($publicFile->isAppPublic())->toBeTrue();
});

it('checks ownership via isOwnedBy', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $file = File::factory()->forUser($owner)->create();

    expect($file->isOwnedBy($owner))->toBeTrue()
        ->and($file->isOwnedBy($other))->toBeFalse();
});

it('scopes forUser to files uploaded by that user', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    File::factory()->forUser($userA)->count(2)->create();
    File::factory()->forUser($userB)->create();

    $forA = File::withoutGlobalScopes()->forUser($userA)->get();
    $forB = File::withoutGlobalScopes()->forUser($userB)->get();

    expect($forA)->toHaveCount(2)
        ->and($forB)->toHaveCount(1);
});

it('scopes accessibleBy to include app_public, org files, and own files', function () {
    $user = User::factory()->create();
    $org = Organization::create([
        'name' => 'Test Org',
        'slug' => 'test-org',
        'owner_user_id' => $user->id,
    ]);

    $otherUser = User::factory()->create();
    $otherOrg = Organization::create([
        'name' => 'Other Org',
        'slug' => 'other-org',
        'owner_user_id' => $otherUser->id,
    ]);

    // File in user's org (accessible)
    $orgFile = File::factory()->forUser($otherUser)->forOrganization($org)->create();

    // App public file (accessible to all)
    $publicFile = File::factory()->appPublic()->forUser($otherUser)->create();

    // Personal file by user (accessible)
    $ownFile = File::factory()->personal()->forUser($user)->create();

    // File in other org (NOT accessible)
    $otherOrgFile = File::factory()->forUser($otherUser)->forOrganization($otherOrg)->create();

    // Personal file by other user (NOT accessible)
    $otherPersonalFile = File::factory()->personal()->forUser($otherUser)->create();

    $accessible = File::withoutGlobalScopes()->accessibleBy($user)->pluck('id')->all();

    expect($accessible)->toContain($orgFile->id, $publicFile->id, $ownFile->id)
        ->and($accessible)->not()->toContain($otherOrgFile->id, $otherPersonalFile->id);
});

it('factory personal state sets organization_id to null', function () {
    $user = User::factory()->create();
    $file = File::factory()->personal()->forUser($user)->create();

    expect($file->organization_id)->toBeNull();
});

it('factory forOrganization state sets the organization_id', function () {
    $user = User::factory()->create();
    $org = Organization::create([
        'name' => 'Org for Factory',
        'slug' => 'org-factory',
        'owner_user_id' => $user->id,
    ]);

    $file = File::factory()->forUser($user)->forOrganization($org)->create();

    expect($file->organization_id)->toBe($org->id);
});

// -- FilePolicy tests --

it('allows any authenticated user to view app_public files', function () {
    $uploader = User::factory()->create();
    $viewer = User::factory()->create();
    $file = File::factory()->appPublic()->forUser($uploader)->create();

    expect($viewer->can('view', $file))->toBeTrue()
        ->and($viewer->can('download', $file))->toBeTrue();
});

it('allows org members to view private org files', function () {
    $owner = User::factory()->create();
    $org = Organization::create([
        'name' => 'Policy Org',
        'slug' => 'policy-org',
        'owner_user_id' => $owner->id,
    ]);

    $member = User::factory()->create();
    $org->members()->attach($member->id, [
        'role' => 'member',
        'status' => 'active',
        'invited_by' => $owner->id,
        'invited_at' => now(),
        'joined_at' => now(),
    ]);

    $file = File::factory()->forUser($owner)->forOrganization($org)->create();

    expect($owner->can('view', $file))->toBeTrue()
        ->and($member->can('view', $file))->toBeTrue();
});

it('denies non-members from viewing private org files', function () {
    $owner = User::factory()->create();
    $org = Organization::create([
        'name' => 'Deny Org',
        'slug' => 'deny-org',
        'owner_user_id' => $owner->id,
    ]);

    $outsider = User::factory()->create();
    $file = File::factory()->forUser($owner)->forOrganization($org)->create();

    expect($outsider->can('view', $file))->toBeFalse()
        ->and($outsider->can('download', $file))->toBeFalse();
});

it('allows only the uploader to view personal private files', function () {
    $uploader = User::factory()->create();
    $other = User::factory()->create();
    $file = File::factory()->personal()->forUser($uploader)->create();

    expect($uploader->can('view', $file))->toBeTrue()
        ->and($other->can('view', $file))->toBeFalse();
});

it('allows only the uploader to delete files', function () {
    $uploader = User::factory()->create();
    $other = User::factory()->create();
    $file = File::factory()->forUser($uploader)->create();

    expect($uploader->can('delete', $file))->toBeTrue()
        ->and($other->can('delete', $file))->toBeFalse();
});

it('denies force delete for all users', function () {
    $uploader = User::factory()->create();
    $file = File::factory()->forUser($uploader)->create();

    expect($uploader->can('forceDelete', $file))->toBeFalse();
});

it('allows viewAny for any authenticated user', function () {
    $user = User::factory()->create();

    expect($user->can('viewAny', File::class))->toBeTrue();
});
