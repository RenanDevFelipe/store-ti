<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Product;
use App\Models\SalesLink;
use App\Models\User;
use App\Services\MercadoPagoCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_and_delete_product(): void
    {
        $this->actingAs(User::factory()->create());

        $product = Product::create([
            'name' => 'Plano antigo',
            'type' => 'internet_plan',
            'price_cents' => 8990,
            'currency' => 'BRL',
            'track_stock' => false,
            'billing_cycle' => 'monthly',
            'active' => true,
        ]);

        $this->putJson('/api/products/'.$product->id, [
            'name' => 'Plano novo',
            'sku' => null,
            'type' => 'internet_plan',
            'description' => 'Atualizado',
            'price' => 109.90,
            'discount_type' => 'percent',
            'discount_value' => 10,
            'track_stock' => false,
            'billing_cycle' => 'monthly',
            'active' => true,
        ])->assertOk()
            ->assertJsonPath('name', 'Plano novo')
            ->assertJsonPath('discount_percent', '10.00');

        $this->deleteJson('/api/products/'.$product->id)->assertNoContent();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_admin_can_update_and_delete_sale(): void
    {
        $this->actingAs(User::factory()->create());

        $product = Product::create([
            'name' => 'Internet Fibra',
            'type' => 'internet_plan',
            'price_cents' => 9990,
            'currency' => 'BRL',
            'track_stock' => false,
            'billing_cycle' => 'monthly',
            'active' => true,
        ]);

        $sale = SalesLink::create([
            'product_id' => $product->id,
            'title' => 'Internet Fibra',
            'quantity' => 1,
            'discount_type' => 'none',
            'original_amount_cents' => 9990,
            'final_amount_cents' => 9990,
            'status' => 'pending',
        ]);

        $this->patchJson('/api/sales-links/'.$sale->public_id, [
            'status' => 'cancelled',
        ])->assertOk()
            ->assertJsonPath('status', 'cancelled');

        $this->deleteJson('/api/sales-links/'.$sale->public_id)->assertNoContent();

        $this->assertDatabaseMissing('sales_links', ['id' => $sale->id]);
    }

    public function test_admin_can_refresh_sale_payment_status(): void
    {
        $this->actingAs(User::factory()->create());

        $product = Product::create([
            'name' => 'Internet Fibra',
            'type' => 'internet_plan',
            'price_cents' => 9990,
            'currency' => 'BRL',
            'track_stock' => false,
            'billing_cycle' => 'monthly',
            'active' => true,
        ]);

        $sale = SalesLink::create([
            'product_id' => $product->id,
            'title' => 'Internet Fibra',
            'quantity' => 1,
            'discount_type' => 'none',
            'original_amount_cents' => 9990,
            'final_amount_cents' => 9990,
            'status' => 'pending',
        ]);

        $payment = Payment::create([
            'sales_link_id' => $sale->id,
            'mp_payment_id' => '123456',
            'status' => 'pending',
            'payment_method_id' => 'pix',
            'payment_type_id' => 'bank_transfer',
            'amount_cents' => 9990,
        ]);

        $mock = Mockery::mock(MercadoPagoCheckoutService::class);
        $mock->shouldReceive('syncPayment')->once()->andReturnUsing(function (Payment $syncedPayment) {
            $syncedPayment->update([
                'status' => 'approved',
                'paid_at' => now(),
            ]);
            $syncedPayment->salesLink->update(['status' => 'paid']);

            return $syncedPayment->refresh();
        });
        $this->app->instance(MercadoPagoCheckoutService::class, $mock);

        $this->postJson('/api/sales-links/'.$sale->public_id.'/refresh')
            ->assertOk()
            ->assertJsonPath('message', 'Venda sincronizada com o Mercado Pago.')
            ->assertJsonPath('sale.status', 'paid')
            ->assertJsonPath('sale.payments.0.status', 'approved');

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'approved',
        ]);
    }

    public function test_admin_refresh_sale_without_payment_returns_clear_message(): void
    {
        $this->actingAs(User::factory()->create());

        $product = Product::create([
            'name' => 'Internet Fibra',
            'type' => 'internet_plan',
            'price_cents' => 9990,
            'currency' => 'BRL',
            'track_stock' => false,
            'billing_cycle' => 'monthly',
            'active' => true,
        ]);

        $sale = SalesLink::create([
            'product_id' => $product->id,
            'title' => 'Internet Fibra',
            'quantity' => 1,
            'discount_type' => 'none',
            'original_amount_cents' => 9990,
            'final_amount_cents' => 9990,
            'status' => 'pending',
        ]);

        $this->postJson('/api/sales-links/'.$sale->public_id.'/refresh')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Esta venda ainda nao tem pagamento Pix para sincronizar.');
    }
}
