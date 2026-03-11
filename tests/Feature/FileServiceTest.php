<?php

use App\Enums\FileAccessLevel;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Wave\File;
use Wave\Services\FileService;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->service = app(FileService::class);
});

// -- FileService::store() --

it('stores an uploaded file and creates a file record', function () {
    $user = User::factory()->create();
    $uploadedFile = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $file = $this->service->store($uploadedFile, $user);

    expect($file)->toBeInstanceOf(File::class)
        ->and($file->exists)->toBeTrue()
        ->and($file->uploaded_by_user_id)->toBe($user->id)
        ->and($file->original_name)->toBe('document.pdf')
        ->and($file->mime_type)->toBe('application/pdf')
        ->and($file->disk)->toBe('local')
        ->and($file->access_level)->toBe(FileAccessLevel::Private)
        ->and($file->organization_id)->toBeNull()
        ->and($file->metadata)->toBeNull();

    Storage::disk('local')->assertExists($file->path);
});

it('stores a file in a specified directory', function () {
    $user = User::factory()->create();
    $uploadedFile = UploadedFile::fake()->image('photo.jpg');

    $file = $this->service->store($uploadedFile, $user, directory: 'avatars');

    expect($file->path)->toStartWith('avatars/');
    Storage::disk('local')->assertExists($file->path);
});

it('stores a file with organization association', function () {
    $user = User::factory()->create();
    $org = Organization::create([
        'name' => 'Test Org',
        'slug' => 'test-org',
        'owner_user_id' => $user->id,
    ]);
    $uploadedFile = UploadedFile::fake()->create('report.csv', 50);

    $file = $this->service->store($uploadedFile, $user, organization: $org);

    expect($file->organization_id)->toBe($org->id);
});

it('stores a file with app_public access level', function () {
    $user = User::factory()->create();
    $uploadedFile = UploadedFile::fake()->image('banner.png');

    $file = $this->service->store($uploadedFile, $user, accessLevel: FileAccessLevel::AppPublic);

    expect($file->access_level)->toBe(FileAccessLevel::AppPublic);
});

it('stores a file with fileable association', function () {
    $user = User::factory()->create();
    $uploadedFile = UploadedFile::fake()->create('data.txt', 10);

    $file = $this->service->store($uploadedFile, $user, fileable: $user);

    expect($file->fileable_type)->toBe($user->getMorphClass())
        ->and($file->fileable_id)->toBe($user->id);
});

// -- FileService::storeFromContent() --

it('stores raw content and creates a file record', function () {
    $user = User::factory()->create();
    $content = 'Hello, world!';

    $file = $this->service->storeFromContent($content, 'greeting.txt', 'text/plain', $user);

    expect($file)->toBeInstanceOf(File::class)
        ->and($file->exists)->toBeTrue()
        ->and($file->original_name)->toBe('greeting.txt')
        ->and($file->mime_type)->toBe('text/plain')
        ->and($file->size_bytes)->toBe(strlen($content))
        ->and($file->disk)->toBe('local');

    Storage::disk('local')->assertExists($file->path);
    expect(Storage::disk('local')->get($file->path))->toBe($content);
});

it('stores content in a specified directory with organization', function () {
    $user = User::factory()->create();
    $org = Organization::create([
        'name' => 'Content Org',
        'slug' => 'content-org',
        'owner_user_id' => $user->id,
    ]);

    $file = $this->service->storeFromContent('data', 'file.dat', 'application/octet-stream', $user, organization: $org, directory: 'exports');

    expect($file->path)->toStartWith('exports/')
        ->and($file->organization_id)->toBe($org->id);
});

// -- FileService::signedUrl() --

it('generates a signed download url for a file', function () {
    $user = User::factory()->create();
    $file = File::factory()->forUser($user)->create();

    $url = $this->service->signedUrl($file);

    expect($url)->toContain('files/'.$file->uuid.'/download')
        ->and($url)->toContain('signature=');
});

it('generates a signed url with custom expiration', function () {
    $user = User::factory()->create();
    $file = File::factory()->forUser($user)->create();

    $url = $this->service->signedUrl($file, 60);

    expect($url)->toContain('signature=')
        ->and($url)->toContain('expires=');
});

// -- FileService::delete() --

it('deletes a file from disk and database', function () {
    $user = User::factory()->create();
    $uploadedFile = UploadedFile::fake()->create('to-delete.pdf', 100);

    $file = $this->service->store($uploadedFile, $user);
    $path = $file->path;
    $fileId = $file->id;

    Storage::disk('local')->assertExists($path);

    $result = $this->service->delete($file);

    expect($result)->toBeTrue();
    Storage::disk('local')->assertMissing($path);
    expect(File::find($fileId))->toBeNull();
});

// -- FileService::exists() --

it('returns true when file exists on disk', function () {
    $user = User::factory()->create();
    $uploadedFile = UploadedFile::fake()->create('existing.pdf', 100);

    $file = $this->service->store($uploadedFile, $user);

    expect($this->service->exists($file))->toBeTrue();
});

it('returns false when file does not exist on disk', function () {
    $user = User::factory()->create();
    $file = File::factory()->forUser($user)->create(['path' => 'nonexistent/file.pdf']);

    expect($this->service->exists($file))->toBeFalse();
});

// -- FileDownloadController --

it('downloads a file via signed url as the uploader', function () {
    $user = User::factory()->create();
    $uploadedFile = UploadedFile::fake()->create('download-me.pdf', 100, 'application/pdf');

    $file = $this->service->store($uploadedFile, $user);
    $url = $this->service->signedUrl($file);

    $response = $this->actingAs($user)->get($url);

    $response->assertOk();
    $response->assertDownload('download-me.pdf');
});

it('returns 403 when user is not authorized to download', function () {
    $uploader = User::factory()->create();
    $unauthorized = User::factory()->create();
    $uploadedFile = UploadedFile::fake()->create('secret.pdf', 100);

    $file = $this->service->store($uploadedFile, $uploader);
    $url = $this->service->signedUrl($file);

    $response = $this->actingAs($unauthorized)->get($url);

    $response->assertForbidden();
});

it('returns 403 when url signature is invalid', function () {
    $user = User::factory()->create();
    $uploadedFile = UploadedFile::fake()->create('signed.pdf', 100);

    $file = $this->service->store($uploadedFile, $user);
    $url = route('files.download', ['file' => $file->uuid]);

    $response = $this->actingAs($user)->get($url);

    $response->assertForbidden();
});

it('returns 404 when file record does not exist', function () {
    $user = User::factory()->create();
    $fakeFile = File::factory()->forUser($user)->make(['uuid' => 'nonexistent-uuid']);

    $url = $this->service->signedUrl($fakeFile);

    $response = $this->actingAs($user)->get($url);

    $response->assertNotFound();
});

it('allows org members to download org files via signed url', function () {
    $owner = User::factory()->create();
    $org = Organization::create([
        'name' => 'Download Org',
        'slug' => 'download-org',
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

    $uploadedFile = UploadedFile::fake()->create('team-doc.pdf', 100, 'application/pdf');
    $file = $this->service->store($uploadedFile, $owner, organization: $org);
    $url = $this->service->signedUrl($file);

    $response = $this->actingAs($member)->get($url);

    $response->assertOk();
    $response->assertDownload('team-doc.pdf');
});

it('allows any authenticated user to download app_public files via signed url', function () {
    $uploader = User::factory()->create();
    $viewer = User::factory()->create();

    $uploadedFile = UploadedFile::fake()->create('public-doc.pdf', 100, 'application/pdf');
    $file = $this->service->store($uploadedFile, $uploader, accessLevel: FileAccessLevel::AppPublic);
    $url = $this->service->signedUrl($file);

    $response = $this->actingAs($viewer)->get($url);

    $response->assertOk();
    $response->assertDownload('public-doc.pdf');
});

it('redirects unauthenticated users to login', function () {
    $user = User::factory()->create();
    $uploadedFile = UploadedFile::fake()->create('auth-required.pdf', 100);

    $file = $this->service->store($uploadedFile, $user);
    $url = $this->service->signedUrl($file);

    $response = $this->get($url);

    $response->assertRedirect(route('login'));
});
