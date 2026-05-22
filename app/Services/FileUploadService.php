<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadService
{
    public function upload(UploadedFile $file, string $folder, ?string $disk = null): string
    {
        $disk = $disk ?? config('filesystems.default', 'public');
        $extension = $file->getClientOriginalExtension();
        $filename = Str::ulid().'.'.$extension;

        $file->storeAs($folder, $filename, ['disk' => $disk]);

        return $folder.'/'.$filename;
    }

    public function delete(?string $path, ?string $disk = null): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $disk = $disk ?? config('filesystems.default', 'public');
        Storage::disk($disk)->delete($path);
    }

    public function url(string $path, ?string $disk = null): string
    {
        $disk = $disk ?? config('filesystems.default', 'public');

        return Storage::disk($disk)->url($path);
    }
}
