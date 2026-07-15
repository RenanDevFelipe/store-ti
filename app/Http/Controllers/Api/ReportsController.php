<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SalesLink;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_if($request->user()->role === 'seller', 403);

        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $to = filled($data['to'] ?? null)
            ? CarbonImmutable::parse($data['to'])->endOfDay()
            : CarbonImmutable::now()->endOfDay();
        $from = filled($data['from'] ?? null)
            ? CarbonImmutable::parse($data['from'])->startOfDay()
            : $to->subDays(29)->startOfDay();
        $tenantId = $request->user()->isSuperAdmin()
            ? null
            : $request->user()->tenant_setting_id;

        abort_if(! $request->user()->isSuperAdmin() && ! $tenantId, 403, 'Usuario sem empresa vinculada.');

        $sales = SalesLink::with(['product', 'payments' => fn ($query) => $query->latest()])
            ->when($tenantId, fn ($query) => $query->whereHas('product', fn ($productQuery) => $productQuery->where('tenant_setting_id', $tenantId)))
            ->whereBetween('created_at', [$from, $to])
            ->latest()
            ->get();

        $approvedPayments = Payment::with('salesLink.product')
            ->where('status', 'approved')
            ->when($tenantId, fn ($query) => $query->whereHas('salesLink.product', fn ($productQuery) => $productQuery->where('tenant_setting_id', $tenantId)))
            ->whereBetween('paid_at', [$from, $to])
            ->latest('paid_at')
            ->get();

        $revenueCents = $approvedPayments->sum('amount_cents');
        $paidSales = $sales->where('status', 'paid')->count();

        return response()->json([
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'summary' => [
                'sales_total' => $sales->count(),
                'paid_sales' => $paidSales,
                'pending_sales' => $sales->where('status', 'pending')->count(),
                'cancelled_sales' => $sales->where('status', 'cancelled')->count(),
                'revenue_cents' => $revenueCents,
                'discount_cents' => $sales->sum('discount_amount_cents'),
                'average_ticket_cents' => $paidSales > 0 ? (int) round($revenueCents / $paidSales) : 0,
                'conversion_rate' => $sales->count() > 0 ? round(($paidSales / $sales->count()) * 100, 1) : 0,
            ],
            'status_breakdown' => collect(['draft', 'ready', 'pending', 'paid', 'cancelled'])
                ->map(fn (string $status) => [
                    'status' => $status,
                    'label' => $this->statusLabel($status),
                    'count' => $sales->where('status', $status)->count(),
                    'amount_cents' => $sales->where('status', $status)->sum('final_amount_cents'),
                ])
                ->values(),
            'daily_revenue' => $this->dailyRevenue($from, $to, $approvedPayments),
            'top_products' => $sales
                ->groupBy('product_id')
                ->map(function ($productSales) use ($approvedPayments): array {
                    $product = $productSales->first()->product;
                    $productPayments = $approvedPayments->filter(
                        fn (Payment $payment) => $payment->salesLink?->product_id === $product?->id
                    );

                    return [
                        'product_id' => $product?->id,
                        'name' => $product?->name ?? 'Produto removido',
                        'type' => $product?->type,
                        'sales_count' => $productSales->count(),
                        'paid_count' => $productSales->where('status', 'paid')->count(),
                        'revenue_cents' => $productPayments->sum('amount_cents'),
                    ];
                })
                ->sortByDesc('revenue_cents')
                ->values()
                ->take(8),
            'recent_payments' => $approvedPayments
                ->take(8)
                ->map(fn (Payment $payment) => [
                    'id' => $payment->id,
                    'mp_payment_id' => $payment->mp_payment_id,
                    'amount_cents' => $payment->amount_cents,
                    'paid_at' => $payment->paid_at,
                    'status' => $payment->status,
                    'product' => $payment->salesLink?->product?->name,
                    'customer' => [
                        'name' => $payment->salesLink?->metadata['customer_name'] ?? null,
                        'email' => $payment->salesLink?->customer_email,
                        'cpf' => $payment->salesLink?->metadata['customer_cpf'] ?? null,
                    ],
                ])
                ->values(),
            'catalog' => [
                'products_total' => Product::when($tenantId, fn ($query) => $query->where('tenant_setting_id', $tenantId))->count(),
                'active_products' => Product::when($tenantId, fn ($query) => $query->where('tenant_setting_id', $tenantId))->where('active', true)->count(),
                'internet_plans' => Product::when($tenantId, fn ($query) => $query->where('tenant_setting_id', $tenantId))->where('type', 'internet_plan')->count(),
                'without_stock_control' => Product::when($tenantId, fn ($query) => $query->where('tenant_setting_id', $tenantId))->where('track_stock', false)->count(),
            ],
        ]);
    }

    private function dailyRevenue(CarbonImmutable $from, CarbonImmutable $to, $payments): array
    {
        $days = [];
        $current = $from;

        while ($current->lte($to)) {
            $date = $current->toDateString();
            $dayPayments = $payments->filter(fn (Payment $payment) => $payment->paid_at?->toDateString() === $date);
            $days[] = [
                'date' => $date,
                'revenue_cents' => $dayPayments->sum('amount_cents'),
                'payments' => $dayPayments->count(),
            ];
            $current = $current->addDay();
        }

        return $days;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Nao configurado',
            'ready' => 'Pronto',
            'pending' => 'Pendente',
            'paid' => 'Pago',
            'cancelled' => 'Cancelado',
            default => $status,
        };
    }
}
