<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\SalesLink;
use App\Services\MercadoPagoCheckoutService;
use MercadoPago\Net\MPResponse;
use MercadoPago\Resources\Preference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class MercadoPagoCheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_preference_payload_defines_back_urls_and_omits_public_callbacks_on_localhost(): void
    {
        config(['app.url' => 'http://127.0.0.1:8000']);

        $product = Product::create([
            'name' => 'Plano Fibra 600',
            'type' => 'internet_plan',
            'price_cents' => 9990,
            'currency' => 'BRL',
            'track_stock' => false,
            'billing_cycle' => 'monthly',
            'active' => true,
        ]);

        $salesLink = SalesLink::create([
            'product_id' => $product->id,
            'title' => 'Plano Fibra 600',
            'quantity' => 1,
            'discount_type' => 'none',
            'original_amount_cents' => 9990,
            'final_amount_cents' => 9990,
        ])->load('product');

        $method = new ReflectionMethod(MercadoPagoCheckoutService::class, 'preferencePayload');
        $payload = $method->invoke(new MercadoPagoCheckoutService(), $salesLink);

        $this->assertArrayHasKey('success', $payload['back_urls']);
        $this->assertStringContainsString('/checkout/resultado', $payload['back_urls']['success']);
        $this->assertArrayNotHasKey('auto_return', $payload);
        $this->assertArrayNotHasKey('notification_url', $payload);
    }

    public function test_it_reads_checkout_url_from_raw_sdk_response_when_typed_property_is_uninitialized(): void
    {
        $preference = new Preference();
        $preference->setResponse(new MPResponse(201, [
            'init_point' => 'https://www.mercadopago.com.br/checkout/v1/redirect?pref_id=123',
        ]));

        $service = new MercadoPagoCheckoutService();
        $contentMethod = new ReflectionMethod(MercadoPagoCheckoutService::class, 'preferenceResponseContent');
        $valueMethod = new ReflectionMethod(MercadoPagoCheckoutService::class, 'preferenceValue');

        $content = $contentMethod->invoke($service, $preference);

        $this->assertSame(
            'https://www.mercadopago.com.br/checkout/v1/redirect?pref_id=123',
            $valueMethod->invoke($service, $preference, $content, 'init_point')
        );
    }
}
