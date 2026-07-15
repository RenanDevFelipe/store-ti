<?php

namespace App\Services;

use App\Models\Product;
use InvalidArgumentException;

class SalesLinkPricing
{
    public function calculate(Product $product, int $quantity, string $discountType, int $discountValueCents, float $discountPercent): array
    {
        $originalAmount = $product->price_cents * $quantity;

        $discountAmount = match ($discountType) {
            'fixed' => min($discountValueCents, $originalAmount),
            'percent' => (int) round($originalAmount * min($discountPercent, 100) / 100),
            'none' => 0,
            default => throw new InvalidArgumentException('Tipo de desconto invalido.'),
        };

        return [
            'original_amount_cents' => $originalAmount,
            'discount_amount_cents' => $discountAmount,
            'final_amount_cents' => max($originalAmount - $discountAmount, 0),
        ];
    }
}
