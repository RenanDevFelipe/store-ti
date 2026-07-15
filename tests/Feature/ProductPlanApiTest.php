<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPlanApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_internet_plan_without_stock_control(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->postJson('/api/products', [
            'name' => 'Internet Fibra 600 Mega',
            'sku' => 'FIBRA-600',
            'type' => 'internet_plan',
            'description' => 'Plano residencial com instalacao inclusa.',
            'price' => 99.90,
            'track_stock' => false,
            'billing_cycle' => 'monthly',
            'active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('type', 'internet_plan')
            ->assertJsonPath('track_stock', false)
            ->assertJsonPath('stock', null)
            ->assertJsonPath('billing_cycle', 'monthly');
    }
}
