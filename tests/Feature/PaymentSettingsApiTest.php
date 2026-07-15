<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_configure_mercado_pago(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->putJson('/api/payment-settings', [
            'access_token' => 'TEST-123456',
            'public_key' => 'TEST-public-key',
            'sandbox' => true,
            'statement_descriptor' => 'STORE TI',
        ]);

        $response->assertOk()
            ->assertJsonPath('configured', true)
            ->assertJsonPath('public_key', 'TEST-public-key')
            ->assertJsonPath('sandbox', true);
    }
}
