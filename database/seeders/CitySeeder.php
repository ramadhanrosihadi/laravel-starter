<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $citiesPath = $this->getSourcePath('dr5hn/cities.json');
        $regenciesPath = $this->getSourcePath('emsifa/regencies.json');

        if (! file_exists($citiesPath)) {
            $this->command->error('cities.json not found. Run: php artisan regions:download');

            return;
        }

        if (! file_exists($regenciesPath)) {
            $this->command->error('emsifa/regencies.json not found. Run: php artisan regions:download');

            return;
        }

        $now = now()->toDateTimeString();

        // --- Build lookup maps from DB ---

        // dr5hn state numeric ID to region.id (stored in meta->source_id by StateSeeder).
        // Uses PHP-side filtering for cross-database compatibility (SQLite in tests, PostgreSQL in production).
        $drStateMap = [];
        DB::table('regions')
            ->where('type', 'state')
            ->whereNotNull('meta')
            ->select(['id', 'meta'])
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$drStateMap) {
                foreach ($rows as $row) {
                    $meta = is_string($row->meta) ? json_decode($row->meta, true) : (array) $row->meta;
                    if (! empty($meta['source_id'])) {
                        $drStateMap[(int) $meta['source_id']] = $row->id;
                    }
                }
            });

        // BPS province code to region.id for Indonesia's provinces
        $indonesiaId = DB::table('regions')->where('type', 'country')->where('code', 'ID')->value('id');

        $idProvinceMap = DB::table('regions')
            ->where('type', 'state')
            ->where('parent_id', $indonesiaId)
            ->pluck('id', 'code')
            ->toArray();

        // --- Non-Indonesia cities from dr5hn ---
        $count = 0;
        $batch = [];

        foreach ($this->readJsonObjects($citiesPath) as $c) {
            if (($c['country_code'] ?? '') === 'ID') {
                continue;
            }

            $parentId = $drStateMap[$c['state_id'] ?? 0] ?? null;

            if ($parentId === null) {
                continue;
            }

            $batch[] = [
                'parent_id' => $parentId,
                'type' => 'city',
                'code' => null,
                'name' => $c['name'],
                'phone_code' => null,
                'meta' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= 1000) {
                DB::table('regions')->insertOrIgnore($batch);
                $count += count($batch);
                $batch = [];
            }
        }

        if (! empty($batch)) {
            DB::table('regions')->insertOrIgnore($batch);
            $count += count($batch);
        }

        $this->command->info("  Non-ID cities seeded: {$count}");

        // --- Indonesian regencies (kabupaten/kota) from emsifa ---
        $regencies = json_decode(file_get_contents($regenciesPath), true);
        $idRows = [];

        foreach ($regencies as $r) {
            $parentId = $idProvinceMap[$r['province_id'] ?? ''] ?? null;

            if ($parentId === null) {
                continue;
            }

            $nameLower = strtolower($r['name']);
            $subtype = str_starts_with($nameLower, 'kota') ? 'kota' : 'kabupaten';

            $idRows[] = [
                'parent_id' => $parentId,
                'type' => 'city',
                'code' => $r['id'],  // BPS regency code e.g. "1101"
                'name' => $r['name'],
                'phone_code' => null,
                'meta' => json_encode(['subtype' => $subtype]),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($idRows, 500) as $chunk) {
            DB::table('regions')->insertOrIgnore($chunk);
        }

        $this->command->info('  Indonesia regencies seeded: '.count($idRows));
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

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    private function readJsonObjects(string $path): \Generator
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return;
        }

        $buffer = '';
        $depth = 0;
        $inString = false;
        $escaped = false;

        while (($char = fgetc($handle)) !== false) {
            if ($depth > 0) {
                $buffer .= $char;
            }

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;

                continue;
            }

            if ($char === '{') {
                $depth++;

                if ($depth === 1 && $buffer === '') {
                    $buffer = '{';
                }

                continue;
            }

            if ($char === '}') {
                $depth--;

                if ($depth === 0) {
                    $decoded = json_decode($buffer, true);

                    if (is_array($decoded)) {
                        yield $decoded;
                    }

                    $buffer = '';
                }
            }
        }

        fclose($handle);
    }
}
