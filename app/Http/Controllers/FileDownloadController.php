<?php

namespace App\Http\Controllers;

use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileDownloadController extends Controller
{
    public function __invoke(Request $request, string $file): StreamedResponse
    {
        $fileRecord = File::where('uuid', $file)->firstOrFail();

        Gate::authorize('download', $fileRecord);

        abort_unless(Storage::disk($fileRecord->disk)->exists($fileRecord->path), 404);

        return Storage::disk($fileRecord->disk)->download($fileRecord->path, $fileRecord->original_name);
    }
}
