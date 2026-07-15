<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\SalesLinkPricing;
use PHPUnit\Framework\TestCase;

class SalesLinkPricingTest extends TestCase
{
    public function test_it_applies_percent_discount(): void
    {
        $product = new Product(['price_cents' => 25000]);

        $amounts = (new SalesLinkPricing())->calculate($product, 2, 'percent', 0, 10);

        $this->assertSame(50000, $amounts['original_amount_cents']);
        $this->assertSame(5000, $amounts['discount_amount_cents']);
        $this->assertSame(45000, $amounts['final_amount_cents']);
    }

    public function test_it_caps_fixed_discount_at_total_amount(): void
    {
        $product = new Product(['price_cents' => 10000]);

        $amounts = (new SalesLinkPricing())->calculate($product, 1, 'fixed', 15000, 0);

        $this->assertSame(10000, $amounts['discount_amount_cents']);
        $this->assertSame(0, $amounts['final_amount_cents']);
    }
}
