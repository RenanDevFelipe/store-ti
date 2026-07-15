<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SalesLink;
use App\Models\StoreTheme;
use App\Models\TenantSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoCompanySeeder extends Seeder
{
    public function run(): void
    {
        $tenant = TenantSetting::updateOrCreate(
            ['store_slug' => 'demo-store'],
            [
                'name' => 'Demo Store TI',
                'active' => true,
                'document' => '12.345.678/0001-90',
                'support_phone' => '(81) 99999-0101',
                'support_email' => 'suporte@demo-store.local',
                'admin_primary_color' => '#102026',
                'admin_accent_color' => '#0f766e',
                'checkout_primary_color' => '#15803d',
                'checkout_button_color' => '#facc15',
                'active_payment_provider' => 'mercado_pago',
                'payment_providers' => TenantSetting::defaultProviders(),
                'payment_credentials' => [],
                'store_theme' => 'copa-do-mundo',
                'store_title' => 'Demo Store TI',
                'store_subtitle' => 'Loja demo com produtos, planos, frete, clientes e pedidos para apresentacao.',
                'store_banner_label' => 'Demo ao vivo',
                'store_banner_image_url' => 'https://images.unsplash.com/photo-1522778119026-d647f0596c20?auto=format&fit=crop&w=1600&q=80',
                'store_featured_image_url' => 'https://images.unsplash.com/photo-1518779578993-ec3579fee39f?auto=format&fit=crop&w=1200&q=80',
                'store_featured_label' => 'Campanha',
                'store_featured_title' => 'Ofertas campeas',
                'store_featured_subtitle' => 'Produtos e planos prontos para vender online.',
                'store_featured_cta' => 'Ver',
                'store_secure_image_url' => 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?auto=format&fit=crop&w=1200&q=80',
                'store_secure_label' => 'Protecao',
                'store_secure_title' => 'Compra segura',
                'store_secure_subtitle' => 'Pix, cliente logado e acompanhamento do pedido.',
                'store_secure_cta' => 'Ver',
                'store_shipping_regions' => [
                    ['region' => 'Retirada / Digital', 'cep_prefix' => '', 'price_cents' => 0, 'eta' => 'Imediato'],
                    ['region' => 'Entrega local', 'cep_prefix' => '50', 'price_cents' => 1500, 'eta' => '1 a 2 dias uteis'],
                    ['region' => 'Grande Recife', 'cep_prefix' => '51', 'price_cents' => 2500, 'eta' => '2 a 4 dias uteis'],
                ],
            ]
        );

        StoreTheme::updateOrCreate(
            ['tenant_setting_id' => $tenant->id, 'slug' => 'copa-do-mundo'],
            [
                'name' => 'Copa do Mundo',
                'primary_color' => '#15803d',
                'accent_color' => '#facc15',
                'background_color' => '#eef7ee',
                'banner_label' => 'Copa do Mundo',
                'banner_image_url' => 'https://images.unsplash.com/photo-1522778119026-d647f0596c20?auto=format&fit=crop&w=1600&q=80',
                'featured_image_url' => 'https://images.unsplash.com/photo-1517466787929-bc90951d0974?auto=format&fit=crop&w=1200&q=80',
                'featured_title' => 'Ofertas campeas',
                'featured_subtitle' => 'Campanha especial para demonstracao da loja.',
                'active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@demo-store.local'],
            [
                'tenant_setting_id' => $tenant->id,
                'name' => 'Admin Demo',
                'password' => 'password',
                'role' => 'admin',
                'active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'vendedor@demo-store.local'],
            [
                'tenant_setting_id' => $tenant->id,
                'name' => 'Vendedor Demo',
                'password' => 'password',
                'role' => 'seller',
                'active' => true,
            ]
        );

        $products = collect([
            [
                'sku' => 'DEMO-PLAN-600',
                'name' => 'Internet Fibra 600Mb',
                'type' => 'internet_plan',
                'description' => 'Plano residencial com instalacao rapida, suporte local e Wi-Fi incluso.',
                'price_cents' => 9990,
                'discount_type' => 'percent',
                'discount_percent' => 10,
                'billing_cycle' => 'monthly',
                'image_url' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=900&q=80',
                'requires_shipping' => false,
                'track_stock' => false,
                'stock' => null,
            ],
            [
                'sku' => 'DEMO-PLAN-300',
                'name' => 'Internet Fibra 300Mb',
                'type' => 'internet_plan',
                'description' => 'Plano economico para casas conectadas, streaming e home office.',
                'price_cents' => 7990,
                'discount_type' => 'none',
                'billing_cycle' => 'monthly',
                'image_url' => 'https://images.unsplash.com/photo-1544197150-b99a580bb7a8?auto=format&fit=crop&w=900&q=80',
                'requires_shipping' => false,
                'track_stock' => false,
                'stock' => null,
            ],
            [
                'sku' => 'DEMO-IPHONE-17',
                'name' => 'Iphone 17 Pro Max',
                'type' => 'physical',
                'description' => 'Smartphone demo com galeria, variacoes de cor e controle de entrega.',
                'price_cents' => 674910,
                'discount_type' => 'none',
                'discount_percent' => 0,
                'billing_cycle' => 'one_time',
                'image_url' => 'https://images.unsplash.com/photo-1592750475338-74b7b21085ab?auto=format&fit=crop&w=900&q=80',
                'gallery_urls' => [
                    'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=900&q=80',
                    'https://images.unsplash.com/photo-1567581935884-3349723552ca?auto=format&fit=crop&w=900&q=80',
                ],
                'options' => [
                    'colors' => ['Preto', 'Azul', 'Titaneo'],
                    'sizes' => ['256GB', '512GB'],
                    'variants' => [
                        ['size' => '256GB', 'color' => 'Preto', 'price_cents' => 674910, 'image_url' => 'https://images.unsplash.com/photo-1592750475338-74b7b21085ab?auto=format&fit=crop&w=900&q=80'],
                        ['size' => '512GB', 'color' => 'Preto', 'price_cents' => 749990, 'image_url' => 'https://images.unsplash.com/photo-1592750475338-74b7b21085ab?auto=format&fit=crop&w=900&q=80'],
                        ['size' => '256GB', 'color' => 'Azul', 'price_cents' => 689990, 'image_url' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=900&q=80'],
                        ['size' => '512GB', 'color' => 'Azul', 'price_cents' => 764990, 'image_url' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=900&q=80'],
                        ['size' => '256GB', 'color' => 'Titaneo', 'price_cents' => 699990, 'image_url' => 'https://images.unsplash.com/photo-1567581935884-3349723552ca?auto=format&fit=crop&w=900&q=80'],
                        ['size' => '512GB', 'color' => 'Titaneo', 'price_cents' => 779990, 'image_url' => 'https://images.unsplash.com/photo-1567581935884-3349723552ca?auto=format&fit=crop&w=900&q=80'],
                    ],
                ],
                'requires_shipping' => true,
                'shipping_weight_grams' => 420,
                'track_stock' => true,
                'stock' => 12,
            ],
            [
                'sku' => 'DEMO-NOTE-I7',
                'name' => 'Notebook Pro i7 16GB',
                'type' => 'physical',
                'description' => 'Notebook para empresas, pronto para demonstrar venda com frete.',
                'price_cents' => 489900,
                'discount_type' => 'fixed',
                'discount_value_cents' => 30000,
                'billing_cycle' => 'one_time',
                'image_url' => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=900&q=80',
                'requires_shipping' => true,
                'shipping_weight_grams' => 1800,
                'track_stock' => true,
                'stock' => 7,
            ],
            [
                'sku' => 'DEMO-ROTEADOR-AX',
                'name' => 'Roteador Wi-Fi 6 AX1800',
                'type' => 'physical',
                'description' => 'Roteador dual band para melhorar cobertura e velocidade da rede.',
                'price_cents' => 34990,
                'discount_type' => 'percent',
                'discount_percent' => 8,
                'billing_cycle' => 'one_time',
                'image_url' => 'https://images.unsplash.com/photo-1606904825846-647eb07f5be2?auto=format&fit=crop&w=900&q=80',
                'requires_shipping' => true,
                'shipping_weight_grams' => 650,
                'track_stock' => true,
                'stock' => 25,
            ],
            [
                'sku' => 'DEMO-CAMERA-WIFI',
                'name' => 'Camera Wi-Fi Full HD',
                'type' => 'physical',
                'description' => 'Camera interna com visao noturna, app mobile e entrega por regiao.',
                'price_cents' => 18990,
                'discount_type' => 'none',
                'billing_cycle' => 'one_time',
                'image_url' => 'https://images.unsplash.com/photo-1558002038-1055907df827?auto=format&fit=crop&w=900&q=80',
                'requires_shipping' => true,
                'shipping_weight_grams' => 360,
                'track_stock' => true,
                'stock' => 18,
            ],
            [
                'sku' => 'DEMO-SUPORTE',
                'name' => 'Suporte Tecnico Premium',
                'type' => 'service',
                'description' => 'Servico mensal para manutencao, atendimento remoto e visitas agendadas.',
                'price_cents' => 24990,
                'discount_type' => 'none',
                'billing_cycle' => 'monthly',
                'image_url' => 'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=900&q=80',
                'requires_shipping' => false,
                'track_stock' => false,
                'stock' => null,
            ],
            [
                'sku' => 'DEMO-INSTALACAO',
                'name' => 'Instalacao Residencial Premium',
                'type' => 'service',
                'description' => 'Visita tecnica para passagem de cabos, organizacao e configuracao.',
                'price_cents' => 12990,
                'discount_type' => 'none',
                'billing_cycle' => 'one_time',
                'image_url' => 'https://images.unsplash.com/photo-1581092921461-eab62e97a780?auto=format&fit=crop&w=900&q=80',
                'requires_shipping' => false,
                'track_stock' => false,
                'stock' => null,
            ],
            [
                'sku' => 'DEMO-CLOUD-1TB',
                'name' => 'Backup Cloud 1TB',
                'type' => 'subscription',
                'description' => 'Assinatura mensal para backup automatico de arquivos importantes.',
                'price_cents' => 5990,
                'discount_type' => 'none',
                'billing_cycle' => 'monthly',
                'image_url' => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=900&q=80',
                'requires_shipping' => false,
                'track_stock' => false,
                'stock' => null,
            ],
        ])->map(function (array $data) use ($tenant): Product {
            $discountType = $data['discount_type'] ?? 'none';

            return Product::updateOrCreate(
                ['sku' => $data['sku']],
                [
                    ...$data,
                    'tenant_setting_id' => $tenant->id,
                    'public_id' => Product::where('sku', $data['sku'])->value('public_id') ?: (string) Str::uuid(),
                    'gallery_urls' => $data['gallery_urls'] ?? [],
                    'options' => $data['options'] ?? ['colors' => [], 'sizes' => []],
                    'discount_value_cents' => $discountType === 'fixed' ? ($data['discount_value_cents'] ?? 0) : 0,
                    'discount_percent' => $discountType === 'percent' ? ($data['discount_percent'] ?? 0) : 0,
                    'currency' => 'BRL',
                    'active' => true,
                ]
            );
        });

        $customer = Customer::updateOrCreate(
            ['tenant_setting_id' => $tenant->id, 'email' => 'cliente@demo-store.local'],
            [
                'name' => 'Cliente Demo',
                'phone' => '(81) 98888-0000',
                'cpf' => '52998224725',
                'password' => 'password',
                'active' => true,
            ]
        );

        $address = $customer->addresses()->updateOrCreate(
            ['label' => 'Casa'],
            [
                'recipient_name' => 'Cliente Demo',
                'phone' => '(81) 98888-0000',
                'cep' => '50000000',
                'street' => 'Rua Demo',
                'number' => '100',
                'complement' => 'Apto 101',
                'neighborhood' => 'Centro',
                'city' => 'Recife',
                'state' => 'PE',
                'default' => true,
            ]
        );

        $this->createDemoSale($products[1], $customer, $address, 'Pedido demo pago com entrega', 'paid', 'approved');
        $this->createDemoSale($products[0], $customer, null, 'Plano demo aguardando pagamento', 'pending', 'pending');
    }

    private function createDemoSale(Product $product, Customer $customer, mixed $address, string $title, string $status, string $paymentStatus): void
    {
        $sale = SalesLink::firstOrCreate(
            ['product_id' => $product->id, 'title' => $title, 'customer_email' => $customer->email],
            [
                'customer_id' => $customer->id,
                'customer_address_id' => $address?->id,
                'quantity' => 1,
                'discount_type' => $product->discount_type,
                'discount_value_cents' => $product->discount_value_cents,
                'discount_percent' => $product->discount_percent,
                'original_amount_cents' => $product->price_cents,
                'discount_amount_cents' => $product->discountAmountCents(),
                'final_amount_cents' => $product->finalAmountCents(),
                'status' => $status,
                'metadata' => [
                    'origin' => 'demo_seed',
                    'customer_name' => $customer->name,
                    'customer_phone' => $customer->phone,
                    'customer_cpf' => $customer->cpf,
                    'shipping_region' => $address ? 'Entrega local' : null,
                    'shipping_eta' => $address ? '1 a 2 dias uteis' : null,
                    'delivery_status' => $address ? 'shipped' : 'waiting_payment',
                    'tracking_code' => $address ? 'DEMO123456BR' : null,
                ],
            ]
        );

        Payment::updateOrCreate(
            ['mp_payment_id' => 'DEMO-'.$sale->id],
            [
                'sales_link_id' => $sale->id,
                'status' => $paymentStatus,
                'status_detail' => $paymentStatus === 'approved' ? 'accredited' : 'pending_waiting_payment',
                'payment_method_id' => 'pix',
                'payment_type_id' => 'bank_transfer',
                'amount_cents' => $sale->final_amount_cents,
                'paid_at' => $paymentStatus === 'approved' ? now()->subDay() : null,
                'raw_payload' => ['demo' => true],
            ]
        );
    }
}
