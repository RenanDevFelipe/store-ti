<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\TenantSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_configure_tenant_theme_and_payment_provider(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superadmin', 'active' => true]));

        $this->putJson('/api/tenant-settings', [
            'name' => 'Provedor Renan',
            'store_slug' => 'provedor-renan',
            'store_theme' => 'default',
            'store_title' => 'Provedor Renan',
            'store_subtitle' => 'Planos e servicos para contratar online.',
            'store_banner_label' => 'Ofertas da semana',
            'document' => '12.345.678/0001-90',
            'support_phone' => '(81) 98252-3877',
            'support_email' => 'suporte@example.com',
            'admin_primary_color' => '#101820',
            'admin_accent_color' => '#00a884',
            'checkout_primary_color' => '#2563eb',
            'checkout_button_color' => '#22c55e',
            'active_payment_provider' => 'asaas',
            'payment_providers' => [
                'mercado_pago' => ['enabled' => true],
                'asaas' => ['enabled' => true],
                'abacate_pay' => ['enabled' => false],
                'paypal' => ['enabled' => false],
            ],
        ])->assertOk()
            ->assertJsonPath('name', 'Provedor Renan')
            ->assertJsonPath('active_payment_provider', 'asaas')
            ->assertJsonPath('payment_providers.asaas.enabled', true);

        $this->getJson('/tenant-settings/public')
            ->assertOk()
            ->assertJsonPath('name', 'Provedor Renan')
            ->assertJsonPath('payment_provider_label', 'Asaas');
    }

    public function test_superadmin_can_create_update_and_activate_companies(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superadmin', 'active' => true]));

        $created = $this->postJson('/api/companies', [
            'name' => 'Empresa B',
            'store_slug' => 'empresa-b',
            'store_theme' => 'fathers_day',
            'store_title' => 'Empresa B Store',
            'store_subtitle' => 'Produtos e planos da Empresa B.',
            'store_banner_label' => 'Dia dos Pais',
            'document' => '00.000.000/0001-00',
            'support_phone' => '(11) 99999-9999',
            'support_email' => 'empresa-b@example.com',
            'admin_primary_color' => '#101820',
            'admin_accent_color' => '#00a884',
            'checkout_primary_color' => '#2563eb',
            'checkout_button_color' => '#22c55e',
            'active_payment_provider' => 'mercado_pago',
            'payment_providers' => [
                'mercado_pago' => ['enabled' => true],
                'asaas' => ['enabled' => false],
                'abacate_pay' => ['enabled' => false],
                'paypal' => ['enabled' => false],
            ],
        ])->assertCreated()
            ->assertJsonPath('name', 'Empresa B')
            ->json();

        $this->putJson('/api/companies/'.$created['id'], [
            'name' => 'Empresa B Atualizada',
            'store_slug' => 'empresa-b',
            'store_theme' => 'black_friday',
            'store_title' => 'Empresa B Atualizada',
            'store_subtitle' => 'Ofertas atualizadas.',
            'store_banner_label' => 'Black Friday',
            'document' => '00.000.000/0001-00',
            'support_phone' => '(11) 99999-9999',
            'support_email' => 'empresa-b@example.com',
            'admin_primary_color' => '#101820',
            'admin_accent_color' => '#00a884',
            'checkout_primary_color' => '#2563eb',
            'checkout_button_color' => '#22c55e',
            'active_payment_provider' => 'mercado_pago',
            'payment_providers' => [
                'mercado_pago' => ['enabled' => true],
                'asaas' => ['enabled' => false],
                'abacate_pay' => ['enabled' => false],
                'paypal' => ['enabled' => false],
            ],
        ])->assertOk()
            ->assertJsonPath('name', 'Empresa B Atualizada');

        $this->postJson('/api/companies/'.$created['id'].'/activate')
            ->assertOk()
            ->assertJsonPath('is_current', true);

        $this->getJson('/api/tenant-settings')
            ->assertOk()
            ->assertJsonPath('name', 'Empresa B Atualizada');
    }

    public function test_public_storefront_lists_active_company_products(): void
    {
        $tenant = TenantSetting::create([
            'name' => 'Loja Alpha',
            'store_slug' => 'loja-alpha',
            'store_theme' => 'clean',
            'store_title' => 'Loja Alpha Online',
            'store_subtitle' => 'Contrate planos online.',
            'store_banner_label' => 'Campanha ativa',
            'checkout_primary_color' => '#2563eb',
            'checkout_button_color' => '#22c55e',
            'active_payment_provider' => 'mercado_pago',
            'payment_providers' => TenantSetting::defaultProviders(),
        ]);

        Product::create([
            'tenant_setting_id' => $tenant->id,
            'name' => 'Plano 600 Mega',
            'type' => 'internet_plan',
            'price_cents' => 9990,
            'currency' => 'BRL',
            'track_stock' => false,
            'billing_cycle' => 'monthly',
            'active' => true,
        ]);

        Product::create([
            'tenant_setting_id' => $tenant->id,
            'name' => 'Produto inativo',
            'type' => 'service',
            'price_cents' => 5000,
            'currency' => 'BRL',
            'track_stock' => false,
            'billing_cycle' => 'one_time',
            'active' => false,
        ]);

        $this->getJson('/loja/loja-alpha/data')
            ->assertOk()
            ->assertJsonPath('tenant.store_title', 'Loja Alpha Online')
            ->assertJsonPath('products.0.name', 'Plano 600 Mega')
            ->assertJsonCount(1, 'products')
            ->assertJsonStructure(['products' => [['public_url']]]);
    }
}
