<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Product;
use App\Models\SalesLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_period_reports(): void
    {
        $this->actingAs(User::factory()->create());

        $product = Product::create([
            'name' => 'Internet Fibra 600 Mega',
            'type' => 'internet_plan',
            'price_cents' => 9990,
            'currency' => 'BRL',
            'track_stock' => false,
            'billing_cycle' => 'monthly',
            'active' => true,
        ]);

        $paidSale = SalesLink::create([
            'product_id' => $product->id,
            'title' => 'Internet Fibra 600 Mega',
            'customer_email' => 'cliente@example.com',
            'quantity' => 1,
            'discount_type' => 'none',
            'original_amount_cents' => 9990,
            'final_amount_cents' => 9990,
            'status' => 'paid',
            'created_at' => '2026-07-10 10:00:00',
            'updated_at' => '2026-07-10 10:00:00',
            'metadata' => [
                'customer_name' => 'Cliente Teste',
                'customer_cpf' => '52998224725',
            ],
        ]);

        SalesLink::create([
            'product_id' => $product->id,
            'title' => 'Internet Fibra 600 Mega',
            'quantity' => 1,
            'discount_type' => 'none',
            'original_amount_cents' => 9990,
            'final_amount_cents' => 9990,
            'status' => 'pending',
            'created_at' => '2026-07-11 10:00:00',
            'updated_at' => '2026-07-11 10:00:00',
        ]);

        Payment::create([
            'sales_link_id' => $paidSale->id,
            'mp_payment_id' => '123456',
            'status' => 'approved',
            'payment_method_id' => 'pix',
            'payment_type_id' => 'bank_transfer',
            'amount_cents' => 9990,
            'paid_at' => '2026-07-10 11:00:00',
        ]);

        $this->getJson('/api/reports?from=2026-07-01&to=2026-07-31')
            ->assertOk()
            ->assertJsonPath('summary.sales_total', 2)
            ->assertJsonPath('summary.paid_sales', 1)
            ->assertJsonPath('summary.pending_sales', 1)
            ->assertJsonPath('summary.revenue_cents', 9990)
            ->assertJsonPath('summary.conversion_rate', 50)
            ->assertJsonPath('top_products.0.name', 'Internet Fibra 600 Mega')
            ->assertJsonPath('recent_payments.0.customer.cpf', '52998224725');
    }
}
