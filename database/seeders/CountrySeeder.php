<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $path = $this->getSourcePath('dr5hn/countries.json');

        if (! file_exists($path)) {
            $this->command->error('countries.json not found. Run: php artisan regions:download');

            return;
        }

        $countries = json_decode(file_get_contents($path), true);

        if (empty($countries)) {
            $this->command->warn('countries.json is empty, skipping.');

            return;
        }

        $now = now()->toDateTimeString();
        $rows = [];

        foreach ($countries as $c) {
            $rows[] = [
                'parent_id' => null,
                'type' => 'country',
                'code' => $c['iso2'] ?? null,
                'name' => $c['name'],
                'phone_code' => $this->filledString($c['phone_code'] ?? $c['phonecode'] ?? null),
                'meta' => json_encode([
                    'iso3' => $c['iso3'] ?? null,
                    'capital' => $c['capital'] ?? null,
                    'currency' => $c['currency'] ?? null,
                    'currency_name' => $c['currency_name'] ?? null,
                    'currency_symbol' => $c['currency_symbol'] ?? null,
                    'region' => $c['region'] ?? null,
                    'subregion' => $c['subregion'] ?? null,
                    'emoji' => $c['emoji'] ?? null,
                    'latitude' => $c['latitude'] ?? null,
                    'longitude' => $c['longitude'] ?? null,
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('regions')->insertOrIgnore($chunk);
        }

        $this->command->info('  Countries seeded: '.count($rows));
    }

    /**
     * Returns the base directory for region source data.
     * In testing environment, redirects to test fixtures.
     */
    protected function getSourcePath(string $relative): string
    {
        if (app()->environment('testing')) {
            return base_path("tests/Fixtures/regions/{$relative}");
        }

        return storage_path("app/regions/{$relative}");
    }

    private function filledString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = (string) $value;

        return $value !== '' ? $value : null;
    }
}
