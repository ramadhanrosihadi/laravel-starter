<?php

namespace Tests\Unit\Services;

use App\Services\FileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileUploadServiceTest extends TestCase
{
    private FileUploadService $fileUploadService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fileUploadService = new FileUploadService;
    }

    public function test_upload_stores_file_with_ulid_name_and_correct_extension(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('avatar.png');
        $folder = 'avatars';

        $resultPath = $this->fileUploadService->upload($file, $folder, 'public');

        // Check if path is returned correctly (e.g. avatars/ULID.png)
        $this->assertStringStartsWith('avatars/', $resultPath);
        $this->assertStringEndsWith('.png', $resultPath);

        // Get filename from path
        $filename = basename($resultPath);
        $this->assertSame(26 + 1 + 3, strlen($filename)); // ULID length (26) + '.' (1) + ext (3)

        // Assert file exists on the fake public disk
        Storage::disk('public')->assertExists($resultPath);
    }

    public function test_upload_uses_default_disk_when_not_provided(): void
    {
        Storage::fake('public');
        config(['filesystems.default' => 'public']);

        $file = UploadedFile::fake()->image('document.pdf');
        $folder = 'documents';

        $resultPath = $this->fileUploadService->upload($file, $folder);

        Storage::disk('public')->assertExists($resultPath);
    }

    public function test_delete_removes_file_from_storage(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('photo.jpg');
        $folder = 'photos';
        $path = $this->fileUploadService->upload($file, $folder, 'public');

        Storage::disk('public')->assertExists($path);

        $this->fileUploadService->delete($path, 'public');

        Storage::disk('public')->assertMissing($path);
    }

    public function test_delete_does_nothing_if_path_is_null_or_empty(): void
    {
        Storage::fake('public');

        // This should not throw any exceptions
        $this->fileUploadService->delete(null, 'public');
        $this->fileUploadService->delete('', 'public');

        $this->assertTrue(true);
    }

    public function test_url_returns_correct_storage_link(): void
    {
        Storage::fake('public');

        $path = 'photos/test-image.jpg';
        $url = $this->fileUploadService->url($path, 'public');

        $this->assertStringContainsString($path, $url);
    }
}
