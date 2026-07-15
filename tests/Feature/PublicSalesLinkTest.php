<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Models\SalesLink;
use App\Services\MercadoPagoCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PublicSalesLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_sales_page_and_data_are_available_without_admin_login(): void
    {
        $product = Product::create([
            'name' => 'Servidor Dell',
            'type' => 'physical',
            'price_cents' => 800000,
            'currency' => 'BRL',
            'track_stock' => true,
            'stock' => 1,
            'billing_cycle' => 'one_time',
            'active' => true,
        ]);

        $salesLink = SalesLink::create([
            'product_id' => $product->id,
            'title' => 'Servidor Dell com setup',
            'quantity' => 1,
            'discount_type' => 'none',
            'original_amount_cents' => 800000,
            'final_amount_cents' => 800000,
        ]);

        $this->get('/v/'.$salesLink->public_id)->assertOk();
        $this->getJson('/v/'.$salesLink->public_id.'/data')
            ->assertOk()
            ->assertJsonPath('title', 'Servidor Dell com setup')
            ->assertJsonPath('payment_configured', false)
            ->assertJsonPath('product.name', 'Servidor Dell');
    }

    public function test_public_sales_link_can_generate_pix_payment(): void
    {
        PaymentSetting::create([
            'provider' => 'mercado_pago',
            'access_token' => 'TEST-token',
            'sandbox' => true,
            'statement_descriptor' => 'STORE TI',
        ]);

        $product = Product::create([
            'name' => 'Internet Fibra 600 Mega',
            'type' => 'internet_plan',
            'price_cents' => 9990,
            'currency' => 'BRL',
            'track_stock' => false,
            'billing_cycle' => 'monthly',
            'active' => true,
        ]);

        $salesLink = SalesLink::create([
            'product_id' => $product->id,
            'title' => 'Internet Fibra 600 Mega',
            'quantity' => 1,
            'discount_type' => 'none',
            'original_amount_cents' => 9990,
            'final_amount_cents' => 9990,
            'status' => 'ready',
        ]);

        $payment = Payment::create([
            'sales_link_id' => $salesLink->id,
            'mp_payment_id' => '123456',
            'status' => 'pending',
            'payment_method_id' => 'pix',
            'payment_type_id' => 'bank_transfer',
            'amount_cents' => 9990,
            'raw_payload' => [
                'point_of_interaction' => [
                    'transaction_data' => [
                        'qr_code' => '000201PIX-CODE',
                        'qr_code_base64' => 'base64-image',
                        'ticket_url' => 'https://www.mercadopago.com.br/pix/123456',
                    ],
                ],
            ],
        ]);

        $mock = Mockery::mock(MercadoPagoCheckoutService::class);
        $mock->shouldReceive('createPixPayment')->once()->andReturn($payment);
        $this->app->instance(MercadoPagoCheckoutService::class, $mock);

        $this->postJson('/v/'.$salesLink->public_id.'/pix', [
            'name' => 'Cliente Teste',
            'email' => 'cliente@example.com',
            'phone' => '(11) 99999-9999',
            'cpf' => '529.982.247-25',
        ])->assertCreated()
            ->assertJsonPath('payment_id', '123456')
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('qr_code', '000201PIX-CODE');
    }

    public function test_opening_product_checkout_does_not_create_a_sale(): void
    {
        $product = Product::create([
            'name' => 'Internet Fibra 800 Mega',
            'public_id' => '11111111-1111-4111-8111-111111111111',
            'type' => 'internet_plan',
            'price_cents' => 12990,
            'currency' => 'BRL',
            'track_stock' => false,
            'billing_cycle' => 'monthly',
            'active' => true,
        ]);

        $this->get('/p/'.$product->public_id)->assertOk();
        $this->getJson('/p/'.$product->public_id.'/data')->assertOk();

        $this->assertDatabaseCount('sales_links', 0);
    }

    public function test_product_checkout_registers_sale_even_when_pix_generation_fails(): void
    {
        PaymentSetting::create([
            'provider' => 'mercado_pago',
            'access_token' => 'TEST-token',
            'sandbox' => false,
            'statement_descriptor' => 'STORE TI',
        ]);

        $product = Product::create([
            'name' => 'Internet Fibra 1 Giga',
            'public_id' => '22222222-2222-4222-8222-222222222222',
            'type' => 'internet_plan',
            'price_cents' => 15990,
            'currency' => 'BRL',
            'track_stock' => false,
            'billing_cycle' => 'monthly',
            'active' => true,
        ]);

        $mock = Mockery::mock(MercadoPagoCheckoutService::class);
        $mock->shouldReceive('createPixPayment')->once()->andThrow(new RuntimeException('Falha Mercado Pago'));
        $this->app->instance(MercadoPagoCheckoutService::class, $mock);

        $this->postJson('/p/'.$product->public_id.'/pix', [
            'name' => 'Cliente Teste',
            'email' => 'cliente@example.com',
            'phone' => '(11) 99999-9999',
            'cpf' => '529.982.247-25',
        ])->assertStatus(422)
            ->assertJsonStructure(['sale_id', 'status', 'message']);

        $this->assertDatabaseHas('sales_links', [
            'product_id' => $product->id,
            'customer_email' => 'cliente@example.com',
            'status' => 'pending',
        ]);
    }

    public function test_product_checkout_rejects_invalid_cpf_before_payment_provider(): void
    {
        $product = Product::create([
            'name' => 'Internet Fibra 700 Mega',
            'public_id' => '33333333-3333-4333-8333-333333333333',
            'type' => 'internet_plan',
            'price_cents' => 11990,
            'currency' => 'BRL',
            'track_stock' => false,
            'billing_cycle' => 'monthly',
            'active' => true,
        ]);

        $this->postJson('/p/'.$product->public_id.'/pix', [
            'name' => 'Cliente Teste',
            'email' => 'cliente@example.com',
            'phone' => '(11) 99999-9999',
            'cpf' => '111.111.111-11',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['cpf']);

        $this->assertDatabaseCount('sales_links', 0);
    }

    public function test_sales_api_includes_customer_information(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $product = Product::create([
            'name' => 'Internet Fibra 500 Mega',
            'type' => 'internet_plan',
            'price_cents' => 8990,
            'currency' => 'BRL',
            'track_stock' => false,
            'billing_cycle' => 'monthly',
            'active' => true,
        ]);

        SalesLink::create([
            'product_id' => $product->id,
            'title' => 'Internet Fibra 500 Mega',
            'customer_email' => 'cliente@example.com',
            'quantity' => 1,
            'discount_type' => 'none',
            'original_amount_cents' => 8990,
            'final_amount_cents' => 8990,
            'status' => 'pending',
            'metadata' => [
                'customer_name' => 'Cliente Teste',
                'customer_phone' => '(11) 99999-9999',
                'customer_cpf' => '52998224725',
            ],
        ]);

        $this->getJson('/api/sales-links')
            ->assertOk()
            ->assertJsonPath('0.customer.name', 'Cliente Teste')
            ->assertJsonPath('0.customer.email', 'cliente@example.com')
            ->assertJsonPath('0.customer.phone', '(11) 99999-9999')
            ->assertJsonPath('0.customer.cpf', '52998224725');
    }
}
