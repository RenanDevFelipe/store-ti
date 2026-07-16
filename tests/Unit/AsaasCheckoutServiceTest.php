<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\SalesLink;
use App\Models\TenantSetting;
use App\Services\AsaasCheckoutService;
use App\Services\PaymentCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AsaasCheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_pix_and_normalizes_asaas_response(): void
    {
        Http::fake([
            'api-sandbox.asaas.com/v3/customers?*' => Http::response(['data' => []]),
            'api-sandbox.asaas.com/v3/customers' => Http::response(['id' => 'cus_123'], 201),
            'api-sandbox.asaas.com/v3/payments' => Http::response([
                'id' => 'pay_123',
                'status' => 'PENDING',
                'value' => 99.90,
                'invoiceUrl' => 'https://sandbox.asaas.com/i/123',
            ], 201),
            'api-sandbox.asaas.com/v3/payments/pay_123/pixQrCode' => Http::response([
                'encodedImage' => 'base64-image',
                'payload' => '000201PIX-ASAAS',
                'expirationDate' => '2026-07-17 23:59:59',
            ]),
        ]);

        $tenant = TenantSetting::create([
            'name' => 'Empresa Asaas',
            'active' => true,
            'active_payment_provider' => 'asaas',
            'payment_providers' => array_replace_recursive(TenantSetting::defaultProviders(), [
                'asaas' => ['enabled' => true],
            ]),
            'payment_credentials' => ['asaas' => ['api_key' => '$aact_hmlg_test']],
        ]);
        $product = Product::create([
            'tenant_setting_id' => $tenant->id,
            'name' => 'Plano 100 Mega',
            'type' => 'internet_plan',
            'price_cents' => 9990,
            'currency' => 'BRL',
            'track_stock' => false,
            'active' => true,
        ]);
        $sale = SalesLink::create([
            'product_id' => $product->id,
            'title' => 'Plano 100 Mega',
            'quantity' => 1,
            'discount_type' => 'none',
            'original_amount_cents' => 9990,
            'final_amount_cents' => 9990,
        ])->load('product.tenant');

        $payment = app(AsaasCheckoutService::class)->createPixPayment($sale, [
            'name' => 'Cliente Teste',
            'email' => 'cliente@example.com',
            'cpf' => '52998224725',
            'phone' => '11999999999',
        ]);
        $response = app(PaymentCheckoutService::class)->response($payment->load('salesLink'));

        $this->assertSame('asaas', $response['provider']);
        $this->assertSame('pay_123', $response['payment_id']);
        $this->assertSame('000201PIX-ASAAS', $response['qr_code']);
        $this->assertSame('base64-image', $response['qr_code_base64']);
        $this->assertDatabaseHas('payments', [
            'provider' => 'asaas',
            'provider_payment_id' => 'pay_123',
            'status' => 'pending',
        ]);

        Http::assertSent(fn (Request $request) => $request->hasHeader('access_token', '$aact_hmlg_test'));
    }

    public function test_it_maps_received_webhook_to_approved_payment(): void
    {
        $tenant = TenantSetting::create([
            'name' => 'Empresa Asaas',
            'active' => true,
            'active_payment_provider' => 'asaas',
            'payment_providers' => array_replace_recursive(TenantSetting::defaultProviders(), ['asaas' => ['enabled' => true]]),
            'payment_credentials' => ['asaas' => ['api_key' => '$aact_hmlg_test', 'webhook_token' => 'secret']],
        ]);
        $product = Product::create([
            'tenant_setting_id' => $tenant->id,
            'name' => 'Servico',
            'type' => 'service',
            'price_cents' => 5000,
            'currency' => 'BRL',
            'track_stock' => false,
            'active' => true,
        ]);
        $sale = SalesLink::create([
            'product_id' => $product->id,
            'title' => 'Servico',
            'quantity' => 1,
            'discount_type' => 'none',
            'original_amount_cents' => 5000,
            'final_amount_cents' => 5000,
        ]);

        $this->postJson('/webhooks/asaas/'.$tenant->id, [
            'event' => 'PAYMENT_RECEIVED',
            'payment' => [
                'id' => 'pay_webhook',
                'externalReference' => $sale->public_id,
                'status' => 'RECEIVED',
                'value' => 50,
                'paymentDate' => '2026-07-16',
            ],
        ], ['asaas-access-token' => 'secret'])
            ->assertOk()
            ->assertJsonPath('linked', true);

        $this->assertDatabaseHas('payments', [
            'provider' => 'asaas',
            'provider_payment_id' => 'pay_webhook',
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('sales_links', ['id' => $sale->id, 'status' => 'paid']);
    }
}
