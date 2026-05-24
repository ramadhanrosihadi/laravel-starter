<?php

namespace Database\Seeders\Traits;

trait ResolvesRegionSourcePath
{
    /**
     * Resolve the source path for region JSON files.
     * Use mini test fixtures in the testing environment.
     */
    protected function getRegionPath(string $relativePath): string
    {
        if (app()->environment('testing')) {
            return base_path('tests/Fixtures/regions/'.$relativePath);
        }

        return storage_path('app/regions/'.$relativePath);
    }
}
