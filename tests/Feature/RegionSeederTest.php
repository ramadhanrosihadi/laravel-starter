<?php

namespace Tests\Feature;

use App\Models\Region;
use Database\Seeders\CountrySeeder;
use Database\Seeders\RegionSeeder;
use Database\Seeders\StateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegionSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // In the testing environment, seeders automatically redirect to
        // tests/Fixtures/regions/ instead of storage/app/regions/.
        // No external file download is required.
        if (! $this->fixturesExist()) {
            $this->markTestSkipped(
                'Region test fixtures not found in tests/Fixtures/regions/. '.
                'Expected: dr5hn/countries.json, dr5hn/states.json, dr5hn/cities.json, '.
                'emsifa/provinces.json, emsifa/regencies.json, emsifa/districts.json, emsifa/villages.json'
            );
        }
    }

    public function test_country_seeder_inserts_countries(): void
    {
        $this->seed(CountrySeeder::class);

        $count = Region::countries()->count();
        $this->assertGreaterThanOrEqual(1, $count, "Expected at least 1 country, got {$count}");

        $indonesia = Region::countries()->where('code', 'ID')->first();
        $this->assertNotNull($indonesia, 'Indonesia (ID) must be present');
        $this->assertNotEmpty($indonesia->phone_code);

        $us = Region::countries()->where('code', 'US')->first();
        $this->assertNotNull($us);
        $meta = $us->meta;
        $this->assertArrayHasKey('emoji', $meta);
    }

    public function test_state_seeder_inserts_states_and_id_provinces(): void
    {
        $this->seed(CountrySeeder::class);
        $this->seed(StateSeeder::class);

        $totalStates = Region::states()->count();
        $this->assertGreaterThanOrEqual(1, $totalStates);

        $indonesiaId = Region::countries()->where('code', 'ID')->value('id');
        $idProvinces = Region::states()->where('parent_id', $indonesiaId)->count();
        $this->assertGreaterThanOrEqual(1, $idProvinces, "Expected at least 1 Indonesian province, got {$idProvinces}");

        // BPS code stored in code column
        $aceh = Region::states()->where('code', '11')->first();
        $this->assertNotNull($aceh, 'Province with BPS code 11 (Aceh) must exist');
    }

    public function test_full_hierarchy_traversal(): void
    {
        $this->seed(RegionSeeder::class);

        // Country to state to city to district to village for Indonesia
        $indonesia = Region::countries()->where('code', 'ID')->first();
        $this->assertNotNull($indonesia);

        $province = $indonesia->children()->where('type', 'state')->first();
        $this->assertNotNull($province, 'Indonesia must have at least one province (state)');

        $city = $province->children()->where('type', 'city')->first();
        $this->assertNotNull($city, 'Province must have at least one city/regency');

        $district = $city->children()->where('type', 'district')->first();
        $this->assertNotNull($district, 'City must have at least one district (kecamatan)');

        $village = $district->children()->where('type', 'village')->first();
        $this->assertNotNull($village, 'District must have at least one village (desa/kelurahan)');

        // Non-Indonesia: country to state to city
        $us = Region::countries()->where('code', 'US')->first();
        $usState = $us?->children()->where('type', 'state')->first();
        $this->assertNotNull($usState, 'US should have at least one state');

        $usCity = $usState->children()->where('type', 'city')->first();
        $this->assertNotNull($usCity, 'US state should have at least one city');
    }

    public function test_full_seeder_record_counts(): void
    {
        $this->seed(RegionSeeder::class);

        $this->assertGreaterThanOrEqual(1, Region::countries()->count(), 'countries');
        $this->assertGreaterThanOrEqual(1, Region::states()->count(), 'states');
        $this->assertGreaterThanOrEqual(1, Region::cities()->count(), 'cities');
        $this->assertGreaterThanOrEqual(1, Region::districts()->count(), 'districts');
        $this->assertGreaterThanOrEqual(1, Region::villages()->count(), 'villages');
    }

    /**
     * Check that all required test fixture files exist.
     * This replaces the old check for external storage/app/regions/ files.
     */
    private function fixturesExist(): bool
    {
        $required = [
            base_path('tests/Fixtures/regions/dr5hn/countries.json'),
            base_path('tests/Fixtures/regions/dr5hn/states.json'),
            base_path('tests/Fixtures/regions/dr5hn/cities.json'),
            base_path('tests/Fixtures/regions/emsifa/provinces.json'),
            base_path('tests/Fixtures/regions/emsifa/regencies.json'),
            base_path('tests/Fixtures/regions/emsifa/districts.json'),
            base_path('tests/Fixtures/regions/emsifa/villages.json'),
        ];

        foreach ($required as $file) {
            if (! file_exists($file)) {
                return false;
            }
        }

        return true;
    }
}
