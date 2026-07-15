<?php

namespace Tests\Feature;

use App\Models\NotificationContact;
use App\Models\NotificationSetting;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SalesLink;
use App\Models\User;
use App\Services\EvolutionNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotificationSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_configure_evolution_notifications_with_multiple_contacts(): void
    {
        $this->actingAs(User::factory()->create());

        $this->putJson('/api/notification-settings', [
            'enabled' => true,
            'base_url' => 'https://evolution.example.com',
            'instance' => 'store-ti',
            'api_key' => 'secret-key',
            'dynamic_customer_enabled' => true,
            'notify_sale_created' => true,
            'notify_payment_approved' => true,
            'sale_created_message' => 'Nova venda para {cliente}',
            'payment_approved_message' => 'Pagamento aprovado de {cliente}',
            'contacts' => [
                ['name' => 'Comercial', 'phone' => '(11) 99999-9999', 'active' => true],
                ['name' => 'Financeiro', 'phone' => '(21) 98888-7777', 'active' => true],
            ],
        ])->assertOk()
            ->assertJsonPath('settings.configured', true)
            ->assertJsonCount(2, 'contacts');

        $this->assertDatabaseHas('notification_contacts', ['name' => 'Comercial']);
        $this->assertDatabaseHas('notification_contacts', ['name' => 'Financeiro']);
    }

    public function test_sale_notification_goes_to_fixed_contacts_and_dynamic_customer(): void
    {
        Http::fake([
            'https://evolution.example.com/*' => Http::response(['sent' => true], 200),
        ]);

        NotificationSetting::create([
            'provider' => 'evolution',
            'enabled' => true,
            'base_url' => 'https://evolution.example.com',
            'instance' => 'store-ti',
            'api_key' => 'secret-key',
            'dynamic_customer_enabled' => true,
            'notify_sale_created' => true,
            'notify_payment_approved' => true,
            'sale_created_message' => 'Nova venda {cliente} {produto} {valor}',
            'payment_approved_message' => 'Pago {cliente}',
        ]);
        NotificationContact::create(['name' => 'Comercial', 'phone' => '(11) 99999-9999', 'active' => true]);
        NotificationContact::create(['name' => 'Inativo', 'phone' => '(31) 99999-9999', 'active' => false]);

        $sale = $this->saleWithCustomer();

        app(EvolutionNotificationService::class)->notifySaleCreated($sale);

        Http::assertSentCount(2);
        $this->assertDatabaseHas('notification_logs', [
            'event' => 'sale_created',
            'recipient_phone' => '5511999999999',
            'recipient_type' => 'fixed',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('notification_logs', [
            'event' => 'sale_created',
            'recipient_phone' => '5521988887777',
            'recipient_type' => 'customer',
            'status' => 'sent',
        ]);
    }

    public function test_payment_approved_notification_is_sent_once_per_payment_and_recipient(): void
    {
        Http::fake([
            'https://evolution.example.com/*' => Http::response(['sent' => true], 200),
        ]);

        NotificationSetting::create([
            'provider' => 'evolution',
            'enabled' => true,
            'base_url' => 'https://evolution.example.com',
            'instance' => 'store-ti',
            'api_key' => 'secret-key',
            'dynamic_customer_enabled' => false,
            'notify_sale_created' => true,
            'notify_payment_approved' => true,
            'sale_created_message' => 'Nova venda',
            'payment_approved_message' => 'Pagamento aprovado {pedido}',
        ]);
        NotificationContact::create(['name' => 'Financeiro', 'phone' => '(11) 99999-9999', 'active' => true]);

        $payment = Payment::create([
            'sales_link_id' => $this->saleWithCustomer()->id,
            'mp_payment_id' => '123456',
            'status' => 'approved',
            'payment_method_id' => 'pix',
            'payment_type_id' => 'bank_transfer',
            'amount_cents' => 9990,
            'paid_at' => now(),
        ]);

        app(EvolutionNotificationService::class)->notifyPaymentUpdated($payment);
        app(EvolutionNotificationService::class)->notifyPaymentUpdated($payment);

        Http::assertSentCount(1);
        $this->assertDatabaseHas('notification_logs', [
            'event' => 'payment_approved',
            'payment_id' => $payment->id,
            'recipient_phone' => '5511999999999',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('notification_logs', [
            'event' => 'payment_approved',
            'payment_id' => $payment->id,
            'recipient_phone' => '5511999999999',
            'status' => 'skipped',
        ]);
    }

    public function test_notification_test_normalizes_phone_before_sending(): void
    {
        $this->actingAs(User::factory()->create());
        Http::fake([
            'https://evolution.example.com/*' => Http::response(['sent' => true], 200),
        ]);

        NotificationSetting::create([
            'provider' => 'evolution',
            'enabled' => true,
            'base_url' => 'https://evolution.example.com',
            'instance' => 'store-ti',
            'api_key' => 'secret-key',
            'dynamic_customer_enabled' => false,
            'notify_sale_created' => true,
            'notify_payment_approved' => true,
            'sale_created_message' => 'Nova venda',
            'payment_approved_message' => 'Pagamento aprovado',
        ]);

        $this->postJson('/api/notification-settings/test', [
            'name' => 'Teste Notificacao',
            'phone' => '81982523877',
        ])->assertOk()
            ->assertJsonPath('recipient_phone', '5581982523877')
            ->assertJsonPath('status', 'sent');

        Http::assertSent(fn ($request) => $request['number'] === '5581982523877');
    }

    public function test_notification_retries_alternate_evolution_payload_after_bad_request(): void
    {
        Http::fakeSequence()
            ->push(['message' => 'Bad request'], 400)
            ->push(['sent' => true], 200);

        NotificationSetting::create([
            'provider' => 'evolution',
            'enabled' => true,
            'base_url' => 'https://evolution.example.com',
            'instance' => 'store-ti',
            'api_key' => 'secret-key',
            'dynamic_customer_enabled' => false,
            'notify_sale_created' => true,
            'notify_payment_approved' => true,
            'sale_created_message' => 'Nova venda',
            'payment_approved_message' => 'Pagamento aprovado',
        ]);
        NotificationContact::create(['name' => 'Comercial', 'phone' => '(11) 99999-9999', 'active' => true]);

        app(EvolutionNotificationService::class)->notifySaleCreated($this->saleWithCustomer());

        Http::assertSentCount(2);
        $this->assertDatabaseHas('notification_logs', [
            'recipient_phone' => '5511999999999',
            'status' => 'sent',
        ]);
    }

    private function saleWithCustomer(): SalesLink
    {
        $product = Product::create([
            'name' => 'Internet Fibra 600 Mega',
            'type' => 'internet_plan',
            'price_cents' => 9990,
            'currency' => 'BRL',
            'track_stock' => false,
            'billing_cycle' => 'monthly',
            'active' => true,
        ]);

        return SalesLink::create([
            'product_id' => $product->id,
            'title' => 'Internet Fibra 600 Mega',
            'customer_email' => 'cliente@example.com',
            'quantity' => 1,
            'discount_type' => 'none',
            'original_amount_cents' => 9990,
            'final_amount_cents' => 9990,
            'status' => 'pending',
            'metadata' => [
                'customer_name' => 'Cliente Teste',
                'customer_phone' => '(21) 98888-7777',
                'customer_cpf' => '52998224725',
            ],
        ])->load('product');
    }
}
