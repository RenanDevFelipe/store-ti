<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\TenantSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_manage_users(): void
    {
        $tenant = TenantSetting::create([
            'name' => 'Cliente A',
            'is_current' => true,
            'payment_providers' => TenantSetting::defaultProviders(),
        ]);
        $this->actingAs(User::factory()->create(['role' => 'superadmin', 'active' => true]));

        $this->postJson('/api/users', [
            'name' => 'Operador',
            'email' => 'operador@example.com',
            'password' => 'secret123',
            'tenant_setting_id' => $tenant->id,
            'role' => 'seller',
            'active' => true,
        ])->assertCreated()
            ->assertJsonPath('email', 'operador@example.com')
            ->assertJsonPath('role', 'seller')
            ->assertJsonPath('tenant.name', 'Cliente A');

        $user = User::where('email', 'operador@example.com')->firstOrFail();

        $this->putJson('/api/users/'.$user->id, [
            'name' => 'Operador Atualizado',
            'email' => 'operador@example.com',
            'password' => null,
            'tenant_setting_id' => $tenant->id,
            'role' => 'admin',
            'active' => false,
        ])->assertOk()
            ->assertJsonPath('active', false);
    }

    public function test_company_admin_can_manage_only_own_company_users(): void
    {
        $tenant = TenantSetting::create([
            'name' => 'Cliente A',
            'is_current' => true,
            'payment_providers' => TenantSetting::defaultProviders(),
        ]);
        $otherTenant = TenantSetting::create([
            'name' => 'Cliente B',
            'is_current' => false,
            'payment_providers' => TenantSetting::defaultProviders(),
        ]);
        $admin = User::factory()->create(['tenant_setting_id' => $tenant->id, 'role' => 'admin', 'active' => true]);
        User::factory()->create(['tenant_setting_id' => $otherTenant->id, 'role' => 'seller', 'active' => true]);

        $this->actingAs($admin);

        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonCount(1);

        $this->postJson('/api/users', [
            'name' => 'Vendedor',
            'email' => 'vendedor@example.com',
            'password' => 'secret123',
            'tenant_setting_id' => $otherTenant->id,
            'role' => 'seller',
            'active' => true,
        ])->assertCreated()
            ->assertJsonPath('tenant_setting_id', $tenant->id)
            ->assertJsonPath('role', 'seller');
    }
}
