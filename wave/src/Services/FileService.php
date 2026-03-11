<?php

namespace Wave\Services;

use App\Enums\FileAccessLevel;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Wave\File;
use Wave\Organization;

class FileService
{
    /**
     * Store an uploaded file and create a File record.
     */
    public function store(
        UploadedFile $file,
        User $user,
        ?Organization $organization = null,
        string $directory = '',
        FileAccessLevel $accessLevel = FileAccessLevel::Private,
        ?Model $fileable = null,
    ): File {
        $uuid = (string) Str::uuid();
        $path = ltrim($directory.'/'.$uuid.'.'.$file->getClientOriginalExtension(), '/');

        Storage::disk('local')->putFileAs(dirname($path), $file, basename($path));

        $attributes = [
            'organization_id' => $organization?->id,
            'uploaded_by_user_id' => $user->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'access_level' => $accessLevel,
            'metadata' => null,
        ];

        if ($fileable) {
            $attributes['fileable_type'] = $fileable->getMorphClass();
            $attributes['fileable_id'] = $fileable->getKey();
        }

        return File::create($attributes);
    }

    /**
     * Store raw content as a file and create a File record.
     */
    public function storeFromContent(
        string $content,
        string $filename,
        string $mimeType,
        User $user,
        ?Organization $organization = null,
        string $directory = '',
        FileAccessLevel $accessLevel = FileAccessLevel::Private,
        ?Model $fileable = null,
    ): File {
        $uuid = (string) Str::uuid();
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $path = ltrim($directory.'/'.$uuid.'.'.$extension, '/');

        Storage::disk('local')->put($path, $content);

        $attributes = [
            'organization_id' => $organization?->id,
            'uploaded_by_user_id' => $user->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $filename,
            'mime_type' => $mimeType,
            'size_bytes' => strlen($content),
            'access_level' => $accessLevel,
            'metadata' => null,
        ];

        if ($fileable) {
            $attributes['fileable_type'] = $fileable->getMorphClass();
            $attributes['fileable_id'] = $fileable->getKey();
        }

        return File::create($attributes);
    }

    /**
     * Generate a temporary signed URL for downloading a file.
     */
    public function signedUrl(File $file, int $expirationMinutes = 30): string
    {
        return URL::temporarySignedRoute(
            'files.download',
            now()->addMinutes($expirationMinutes),
            ['file' => $file->uuid],
        );
    }

    /**
     * Delete a file from disk and its database record.
     */
    public function delete(File $file): bool
    {
        Storage::disk($file->disk)->delete($file->path);

        return $file->delete();
    }

    /**
     * Check if a file exists on disk.
     */
    public function exists(File $file): bool
    {
        return Storage::disk($file->disk)->exists($file->path);
    }
}
