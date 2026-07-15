<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesLinkApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_product(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->postJson('/api/products', [
            'name' => 'Notebook Pro',
            'sku' => 'NB-PRO',
            'type' => 'physical',
            'description' => 'Equipamento para venda consultiva.',
            'price' => 3499.90,
            'track_stock' => true,
            'stock' => 3,
            'billing_cycle' => 'one_time',
            'active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'Notebook Pro')
            ->assertJsonPath('price_cents', 349990);
    }

    public function test_it_creates_a_sales_link_as_draft_without_mercado_pago_token(): void
    {
        config(['mercadopago.access_token' => null]);
        $this->actingAs(User::factory()->create());

        $product = Product::create([
            'name' => 'Monitor 27',
            'sku' => 'MON-27',
            'type' => 'physical',
            'price_cents' => 120000,
            'currency' => 'BRL',
            'track_stock' => true,
            'stock' => 5,
            'billing_cycle' => 'one_time',
            'active' => true,
        ]);

        $response = $this->postJson('/api/sales-links', [
            'product_id' => $product->id,
            'title' => 'Monitor 27 com desconto',
            'quantity' => 1,
            'discount_type' => 'percent',
            'discount_value' => 15,
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'draft')
            ->assertJsonStructure(['public_url'])
            ->assertJsonPath('original_amount_cents', 120000)
            ->assertJsonPath('discount_amount_cents', 18000)
            ->assertJsonPath('final_amount_cents', 102000)
            ->assertJsonPath('metadata.payment_setup', 'missing_mercado_pago_access_token');
    }
}
