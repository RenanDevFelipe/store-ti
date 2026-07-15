<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\TenantSetting;
use App\Rules\ValidCpf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin() || $request->user()->isCompanyAdmin(), 403);

        $tenantId = $request->user()->isSuperAdmin()
            ? TenantSetting::current()->id
            : $request->user()->tenant_setting_id;

        return response()->json(
            Customer::with('addresses')
                ->where('tenant_setting_id', $tenantId)
                ->latest()
                ->get()
                ->map(fn (Customer $customer) => $this->present($customer))
        );
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin() || $request->user()->isCompanyAdmin(), 403);
        $this->authorizeTenant($request, $customer);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => [
                'required',
                'email',
                'max:160',
                Rule::unique('customers', 'email')
                    ->where('tenant_setting_id', $customer->tenant_setting_id)
                    ->ignore($customer),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'cpf' => ['nullable', 'string', 'max:20', new ValidCpf()],
            'active' => ['required', 'boolean'],
        ]);

        $customer->update([
            ...$data,
            'cpf' => filled($data['cpf'] ?? null) ? preg_replace('/\D+/', '', $data['cpf']) : null,
        ]);

        return response()->json($this->present($customer->refresh()->load('addresses')));
    }

    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin() || $request->user()->isCompanyAdmin(), 403);
        $this->authorizeTenant($request, $customer);
        $customer->delete();

        return response()->json(status: 204);
    }

    private function authorizeTenant(Request $request, Customer $customer): void
    {
        $tenantId = $request->user()->isSuperAdmin()
            ? TenantSetting::current()->id
            : $request->user()->tenant_setting_id;

        abort_unless($customer->tenant_setting_id === $tenantId, 403);
    }

    private function present(Customer $customer): array
    {
        return [
            ...$customer->only(['id', 'tenant_setting_id', 'name', 'email', 'phone', 'cpf', 'active', 'created_at']),
            'addresses' => $customer->addresses->values(),
        ];
    }
}
