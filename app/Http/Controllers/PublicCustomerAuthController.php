<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\SalesLink;
use App\Models\TenantSetting;
use App\Rules\ValidCpf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PublicCustomerAuthController extends Controller
{
    public function session(Request $request): JsonResponse
    {
        $customer = Auth::guard('customer')->user();

        return response()->json([
            'authenticated' => (bool) $customer,
            'customer' => $customer ? $this->presentCustomer($customer->load('addresses')) : null,
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $tenant = $this->tenantFromRequest($request);

        $data = $request->validate([
            'store_slug' => ['required', 'string'],
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160', Rule::unique('customers', 'email')->where('tenant_setting_id', $tenant->id)],
            'password' => ['required', 'string', 'min:6', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'cpf' => ['nullable', 'string', 'max:20', new ValidCpf()],
        ]);

        $customer = Customer::create([
            ...$data,
            'tenant_setting_id' => $tenant->id,
            'cpf' => filled($data['cpf'] ?? null) ? preg_replace('/\D+/', '', $data['cpf']) : null,
            'active' => true,
        ]);

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return response()->json($this->presentCustomer($customer->load('addresses')), 201);
    }

    public function login(Request $request): JsonResponse
    {
        $tenant = $this->tenantFromRequest($request);
        $data = $request->validate([
            'store_slug' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $customer = Customer::where('tenant_setting_id', $tenant->id)
            ->where('email', $data['email'])
            ->first();

        if (! $customer || ! Hash::check($data['password'], $customer->password) || ! $customer->active || ! $tenant->active) {
            throw ValidationException::withMessages([
                'email' => 'Credenciais invalidas.',
            ]);
        }

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return response()->json($this->presentCustomer($customer->load('addresses')));
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('customer')->logout();

        return response()->json(['ok' => true]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $customer = $this->customer();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160', Rule::unique('customers', 'email')->where('tenant_setting_id', $customer->tenant_setting_id)->ignore($customer->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'cpf' => ['nullable', 'string', 'max:20', new ValidCpf()],
            'password' => ['nullable', 'string', 'min:6', 'max:100'],
        ]);

        $customer->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'cpf' => filled($data['cpf'] ?? null) ? preg_replace('/\D+/', '', $data['cpf']) : null,
            ...(filled($data['password'] ?? null) ? ['password' => $data['password']] : []),
        ]);

        return response()->json($this->presentCustomer($customer->refresh()->load('addresses')));
    }

    public function orders(): JsonResponse
    {
        $customer = $this->customer();

        $orders = SalesLink::with([
            'product',
            'customerAddress',
            'payments' => fn ($query) => $query->latest(),
        ])
            ->where('customer_id', $customer->id)
            ->whereHas('product', fn ($query) => $query->where('tenant_setting_id', $customer->tenant_setting_id))
            ->latest()
            ->get()
            ->map(fn (SalesLink $order) => $this->presentOrder($order));

        return response()->json($orders);
    }

    public function storeAddress(Request $request): JsonResponse
    {
        $customer = $this->customer();
        $data = $this->validatedAddress($request);

        if ($data['default'] ?? false) {
            $customer->addresses()->update(['default' => false]);
        }

        $address = $customer->addresses()->create($data);

        return response()->json($address, 201);
    }

    public function updateAddress(Request $request, CustomerAddress $address): JsonResponse
    {
        $customer = $this->customer();
        abort_unless($address->customer_id === $customer->id, 403);

        $data = $this->validatedAddress($request);

        if ($data['default'] ?? false) {
            $customer->addresses()->whereKeyNot($address->id)->update(['default' => false]);
        }

        $address->update($data);

        return response()->json($address->refresh());
    }

    public function destroyAddress(CustomerAddress $address): JsonResponse
    {
        $customer = $this->customer();
        abort_unless($address->customer_id === $customer->id, 403);
        $address->delete();

        return response()->json(status: 204);
    }

    private function customer(): Customer
    {
        $customer = Auth::guard('customer')->user();
        abort_unless($customer, 401, 'Entre na sua conta de cliente.');

        return $customer;
    }

    private function tenantFromRequest(Request $request): TenantSetting
    {
        return TenantSetting::where('store_slug', $request->input('store_slug'))
            ->where('active', true)
            ->firstOrFail();
    }

    private function validatedAddress(Request $request): array
    {
        return $request->validate([
            'label' => ['nullable', 'string', 'max:80'],
            'recipient_name' => ['nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:30'],
            'cep' => ['required', 'string', 'max:12'],
            'street' => ['required', 'string', 'max:180'],
            'number' => ['required', 'string', 'max:30'],
            'complement' => ['nullable', 'string', 'max:120'],
            'neighborhood' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'size:2'],
            'default' => ['nullable', 'boolean'],
        ]);
    }

    private function presentCustomer(Customer $customer): array
    {
        return [
            ...$customer->only(['id', 'tenant_setting_id', 'name', 'email', 'phone', 'cpf', 'active']),
            'addresses' => $customer->addresses->values(),
        ];
    }

    private function presentOrder(SalesLink $order): array
    {
        $payment = $order->payments->first();
        $metadata = $order->metadata ?? [];
        $deliveryStatus = $metadata['delivery_status'] ?? match (true) {
            $order->status === 'cancelled' => 'cancelled',
            $order->status === 'paid' && $order->product?->requires_shipping => 'preparing',
            $order->status === 'paid' => 'delivered',
            default => 'waiting_payment',
        };

        return [
            'id' => $order->id,
            'public_id' => $order->public_id,
            'title' => $order->title,
            'quantity' => $order->quantity,
            'status' => $order->status,
            'final_amount_cents' => $order->final_amount_cents,
            'created_at' => $order->created_at,
            'public_url' => $order->publicUrl(),
            'product' => $order->product ? [
                'id' => $order->product->id,
                'name' => $order->product->name,
                'image_url' => $order->product->image_url,
                'requires_shipping' => $order->product->requires_shipping,
            ] : null,
            'payment' => $payment ? [
                'status' => $payment->status,
                'status_detail' => $payment->status_detail,
                'paid_at' => $payment->paid_at,
            ] : null,
            'delivery' => [
                'required' => (bool) $order->product?->requires_shipping,
                'status' => $deliveryStatus,
                'region' => $metadata['shipping_region'] ?? null,
                'eta' => $metadata['shipping_eta'] ?? null,
                'tracking_code' => $metadata['tracking_code'] ?? null,
                'tracking_url' => $metadata['tracking_url'] ?? null,
                'note' => $metadata['delivery_note'] ?? null,
                'address' => $order->customerAddress,
            ],
        ];
    }
}
