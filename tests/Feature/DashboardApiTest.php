<?php

namespace Tests\Feature;

use App\Models\TenantSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_dashboard_returns_platform_view(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superadmin', 'active' => true]));

        TenantSetting::query()->delete();
        TenantSetting::create([
            'name' => 'Cliente A',
            'is_current' => true,
            'payment_providers' => TenantSetting::defaultProviders(),
        ]);
        TenantSetting::create([
            'name' => 'Cliente B',
            'is_current' => false,
            'payment_providers' => TenantSetting::defaultProviders(),
        ]);
        User::factory()->create(['role' => 'admin', 'active' => true]);
        User::factory()->create(['role' => 'admin', 'active' => false]);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('mode', 'superadmin')
            ->assertJsonPath('companies_total', 2)
            ->assertJsonPath('active_company', null)
            ->assertJsonPath('admins', 2)
            ->assertJsonPath('inactive_users', 1);
    }

    public function test_admin_dashboard_returns_company_view(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'active' => true]));

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('mode', 'company')
            ->assertJsonMissingPath('companies_total');
    }
}
