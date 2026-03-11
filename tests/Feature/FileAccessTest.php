<?php

use App\Enums\FileAccessLevel;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Wave\File;
use Wave\Services\FileService;
use Wave\TenantContext;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    app(TenantContext::class)->set(null);
    $this->service = app(FileService::class);
});

// -- File model and tenant scoping --

describe('File model and tenant scoping', function () {
    it('scopes files to tenant context', function () {
        $ownerA = User::factory()->create();
        $orgA = Organization::create([
            'name' => 'Org A',
            'slug' => 'org-a',
            'owner_user_id' => $ownerA->id,
        ]);

        $ownerB = User::factory()->create();
        $orgB = Organization::create([
            'name' => 'Org B',
            'slug' => 'org-b',
            'owner_user_id' => $ownerB->id,
        ]);

        File::factory()->forUser($ownerA)->forOrganization($orgA)->count(2)->create();
        File::factory()->forUser($ownerB)->forOrganization($orgB)->count(3)->create();

        app(TenantContext::class)->set($orgA->id);
        expect(File::query()->get())->toHaveCount(2);

        app(TenantContext::class)->set($orgB->id);
        expect(File::query()->get())->toHaveCount(3);
    });

    it('auto-fills organization_id from tenant context on create', function () {
        $user = User::factory()->create();
        $org = Organization::create([
            'name' => 'Auto-fill Org',
            'slug' => 'auto-fill-org',
            'owner_user_id' => $user->id,
        ]);

        app(TenantContext::class)->set($org->id);

        $file = File::create([
            'uploaded_by_user_id' => $user->id,
            'disk' => 'local',
            'path' => 'test/auto-fill.txt',
            'original_name' => 'auto-fill.txt',
            'mime_type' => 'text/plain',
            'size_bytes' => 10,
            'access_level' => FileAccessLevel::Private,
        ]);

        expect($file->organization_id)->toBe($org->id);
    });

    it('allows files without organization for personal context', function () {
        $user = User::factory()->create();

        $file = File::factory()->personal()->forUser($user)->create();

        expect($file->organization_id)->toBeNull();

        app(TenantContext::class)->set(null);
        $allFiles = File::withoutGlobalScopes()->get();
        expect($allFiles->contains('id', $file->id))->toBeTrue();
    });
});

// -- FileService store and signedUrl --

describe('FileService store and signedUrl', function () {
    it('stores a file on the local disk and creates File record', function () {
        $user = User::factory()->create();
        $org = Organization::create([
            'name' => 'Store Org',
            'slug' => 'store-org',
            'owner_user_id' => $user->id,
        ]);

        $uploadedFile = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        $file = $this->service->store($uploadedFile, $user, $org, 'documents');

        expect($file)->toBeInstanceOf(File::class)
            ->and($file->exists)->toBeTrue()
            ->and($file->uploaded_by_user_id)->toBe($user->id)
            ->and($file->organization_id)->toBe($org->id)
            ->and($file->original_name)->toBe('test.pdf')
            ->and($file->mime_type)->toBe('application/pdf')
            ->and($file->path)->toStartWith('documents/');

        Storage::disk('local')->assertExists($file->path);
    });

    it('stores content from string and creates File record', function () {
        $user = User::factory()->create();

        $file = $this->service->storeFromContent('hello world', 'test.txt', 'text/plain', $user);

        expect($file)->toBeInstanceOf(File::class)
            ->and($file->exists)->toBeTrue()
            ->and($file->original_name)->toBe('test.txt')
            ->and($file->mime_type)->toBe('text/plain')
            ->and($file->size_bytes)->toBe(11);

        Storage::disk('local')->assertExists($file->path);
        expect(Storage::disk('local')->get($file->path))->toBe('hello world');
    });

    it('generates valid signed URL', function () {
        $user = User::factory()->create();
        $file = File::factory()->forUser($user)->create();

        $url = $this->service->signedUrl($file);

        expect($url)->toContain($file->uuid)
            ->and($url)->toContain('signature=')
            ->and($url)->toContain('expires=');
    });

    it('deletes file from disk and database', function () {
        $user = User::factory()->create();
        $uploadedFile = UploadedFile::fake()->create('delete-me.pdf', 100);

        $file = $this->service->store($uploadedFile, $user);
        $path = $file->path;
        $fileId = $file->id;

        Storage::disk('local')->assertExists($path);

        $this->service->delete($file);

        Storage::disk('local')->assertMissing($path);
        expect(File::withoutGlobalScopes()->find($fileId))->toBeNull();
    });
});

// -- FilePolicy authorization --

describe('FilePolicy authorization', function () {
    it('allows org member to download private org file', function () {
        $owner = User::factory()->create();
        $org = Organization::create([
            'name' => 'Auth Org',
            'slug' => 'auth-org',
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

        expect(Gate::forUser($member)->allows('download', $file))->toBeTrue();
    });

    it('denies non-member from downloading private org file', function () {
        $owner = User::factory()->create();
        $org = Organization::create([
            'name' => 'Deny Org',
            'slug' => 'deny-org',
            'owner_user_id' => $owner->id,
        ]);

        $outsider = User::factory()->create();
        $file = File::factory()->forUser($owner)->forOrganization($org)->create();

        expect(Gate::forUser($outsider)->denies('download', $file))->toBeTrue();
    });

    it('allows any authenticated user to download app_public file', function () {
        $uploader = User::factory()->create();
        $randomUser = User::factory()->create();

        $file = File::factory()->appPublic()->forUser($uploader)->create();

        expect(Gate::forUser($randomUser)->allows('download', $file))->toBeTrue();
    });

    it('allows uploader to download their personal file', function () {
        $uploader = User::factory()->create();
        $file = File::factory()->personal()->forUser($uploader)->create();

        expect(Gate::forUser($uploader)->allows('download', $file))->toBeTrue();
    });

    it('denies other users from downloading personal private file', function () {
        $uploader = User::factory()->create();
        $otherUser = User::factory()->create();

        $file = File::factory()->personal()->forUser($uploader)->create();

        expect(Gate::forUser($otherUser)->denies('download', $file))->toBeTrue();
    });

    it('allows only uploader to delete file', function () {
        $uploaderA = User::factory()->create();
        $uploaderB = User::factory()->create();
        $org = Organization::create([
            'name' => 'Delete Org',
            'slug' => 'delete-org',
            'owner_user_id' => $uploaderA->id,
        ]);
        $org->members()->attach($uploaderB->id, [
            'role' => 'member',
            'status' => 'active',
            'invited_by' => $uploaderA->id,
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        $file = File::factory()->forUser($uploaderA)->forOrganization($org)->create();

        expect(Gate::forUser($uploaderA)->allows('delete', $file))->toBeTrue()
            ->and(Gate::forUser($uploaderB)->denies('delete', $file))->toBeTrue();
    });
});

// -- Download route --

describe('Download route', function () {
    it('serves file via signed download URL', function () {
        $user = User::factory()->create();
        $uploadedFile = UploadedFile::fake()->create('serve-me.pdf', 100, 'application/pdf');

        $file = $this->service->store($uploadedFile, $user);
        $url = $this->service->signedUrl($file);

        $response = $this->actingAs($user)->get($url);

        $response->assertOk();
        $response->assertDownload('serve-me.pdf');
    });

    it('returns 403 for expired signed URL', function () {
        $user = User::factory()->create();
        $uploadedFile = UploadedFile::fake()->create('expiring.pdf', 100);

        $file = $this->service->store($uploadedFile, $user);
        $url = $this->service->signedUrl($file, 30);

        $this->travel(31)->minutes();

        $response = $this->actingAs($user)->get($url);

        $response->assertForbidden();
    });

    it('returns 403 for tampered signed URL', function () {
        $user = User::factory()->create();
        $uploadedFile = UploadedFile::fake()->create('tampered.pdf', 100);

        $file = $this->service->store($uploadedFile, $user);
        $url = $this->service->signedUrl($file);

        // Tamper with the signature
        $tamperedUrl = preg_replace('/signature=[^&]+/', 'signature=tampered123', $url);

        $response = $this->actingAs($user)->get($tamperedUrl);

        $response->assertForbidden();
    });

    it('returns 403 when unauthorized user accesses private file', function () {
        $owner = User::factory()->create();
        $org = Organization::create([
            'name' => 'Private Org',
            'slug' => 'private-org',
            'owner_user_id' => $owner->id,
        ]);

        $outsider = User::factory()->create();

        $uploadedFile = UploadedFile::fake()->create('private-doc.pdf', 100);
        $file = $this->service->store($uploadedFile, $owner, $org);
        $url = $this->service->signedUrl($file);

        $response = $this->actingAs($outsider)->get($url);

        $response->assertForbidden();
    });

    it('returns 404 for non-existent file UUID', function () {
        $user = User::factory()->create();
        $fakeFile = File::factory()->forUser($user)->make(['uuid' => 'nonexistent-uuid-12345']);

        $url = $this->service->signedUrl($fakeFile);

        $response = $this->actingAs($user)->get($url);

        $response->assertNotFound();
    });

    it('returns 404 when file record exists but disk file is missing', function () {
        $user = User::factory()->create();
        $file = File::factory()->forUser($user)->create([
            'path' => 'missing/file-on-disk.pdf',
        ]);

        $url = $this->service->signedUrl($file);

        $response = $this->actingAs($user)->get($url);

        $response->assertNotFound();
    });
});

// -- Cross-tenant isolation (integration) --

describe('Cross-tenant isolation', function () {
    it('prevents cross-tenant file access via download route', function () {
        $ownerA = User::factory()->create();
        $orgA = Organization::create([
            'name' => 'Isolation Org A',
            'slug' => 'isolation-org-a',
            'owner_user_id' => $ownerA->id,
        ]);

        $ownerB = User::factory()->create();
        $orgB = Organization::create([
            'name' => 'Isolation Org B',
            'slug' => 'isolation-org-b',
            'owner_user_id' => $ownerB->id,
        ]);

        $uploadedFile = UploadedFile::fake()->create('org-a-secret.pdf', 100, 'application/pdf');
        $file = $this->service->store($uploadedFile, $ownerA, $orgA);
        $url = $this->service->signedUrl($file);

        $response = $this->actingAs($ownerB)->get($url);

        $response->assertForbidden();
    });

    it('allows cross-org access for app_public files via download route', function () {
        $ownerA = User::factory()->create();
        $orgA = Organization::create([
            'name' => 'Public Org A',
            'slug' => 'public-org-a',
            'owner_user_id' => $ownerA->id,
        ]);

        $userB = User::factory()->create();

        $uploadedFile = UploadedFile::fake()->create('public-asset.pdf', 100, 'application/pdf');
        $file = $this->service->store($uploadedFile, $ownerA, $orgA, accessLevel: FileAccessLevel::AppPublic);
        $url = $this->service->signedUrl($file);

        $response = $this->actingAs($userB)->get($url);

        $response->assertOk();
        $response->assertDownload('public-asset.pdf');
    });
});

// -- FileService edge cases --

describe('FileService edge cases', function () {
    it('handles duplicate file paths by using UUID-based filenames', function () {
        $user = User::factory()->create();
        $fileA = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');
        $fileB = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');

        $recordA = $this->service->store($fileA, $user, directory: 'documents');
        $recordB = $this->service->store($fileB, $user, directory: 'documents');

        expect($recordA->path)->not()->toBe($recordB->path)
            ->and($recordA->original_name)->toBe('report.pdf')
            ->and($recordB->original_name)->toBe('report.pdf');

        Storage::disk('local')->assertExists($recordA->path);
        Storage::disk('local')->assertExists($recordB->path);
    });

    it('creates file with fileable relationship', function () {
        $user = User::factory()->create();
        $uploadedFile = UploadedFile::fake()->create('avatar.jpg', 50, 'image/jpeg');

        $file = $this->service->store($uploadedFile, $user, fileable: $user);

        expect($file->fileable)->toBeInstanceOf(User::class)
            ->and($file->fileable->id)->toBe($user->id);

        $userFile = $user->morphOne(File::class, 'fileable')->first();
        expect($userFile)->not()->toBeNull()
            ->and($userFile->id)->toBe($file->id);
    });
});
