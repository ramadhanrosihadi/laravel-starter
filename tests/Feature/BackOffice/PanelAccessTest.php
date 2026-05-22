<?php

namespace Tests\Feature\BackOffice;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function panel(): Panel
    {
        return Filament::getPanel('admin');
    }

    public function test_user_with_back_office_role_can_access_panel(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->assertTrue($user->canAccessPanel($this->panel()));
    }

    public function test_user_without_role_cannot_access_panel(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->canAccessPanel($this->panel()));
    }

    public function test_inactive_user_cannot_access_panel(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $user->assignRole('admin');

        $this->assertFalse($user->canAccessPanel($this->panel()));
    }
}
