<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TenantSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin() || $request->user()->isCompanyAdmin(), 403);

        $users = User::with('tenant')
            ->when(! $request->user()->isSuperAdmin(), fn ($query) => $query->where('tenant_setting_id', $request->user()->tenant_setting_id))
            ->latest()
            ->get();

        return response()->json($users->map(fn (User $user) => $this->present($user)));
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin() || $request->user()->isCompanyAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'max:100'],
            'tenant_setting_id' => ['nullable', 'exists:tenant_settings,id'],
            'role' => ['required', 'in:superadmin,admin,seller'],
            'active' => ['required', 'boolean'],
        ]);

        if (! $request->user()->isSuperAdmin()) {
            if ($data['role'] === 'superadmin') {
                abort(403);
            }

            $data['tenant_setting_id'] = $request->user()->tenant_setting_id;
        } elseif (blank($data['tenant_setting_id'] ?? null)) {
            $data['tenant_setting_id'] = null;
        }

        $user = User::create($data);

        return response()->json($this->present($user), 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin() || $request->user()->isCompanyAdmin(), 403);

        if (! $request->user()->isSuperAdmin() && $user->tenant_setting_id !== $request->user()->tenant_setting_id) {
            abort(403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($user)],
            'password' => ['nullable', 'string', 'min:6', 'max:100'],
            'tenant_setting_id' => ['nullable', 'exists:tenant_settings,id'],
            'role' => ['required', 'in:superadmin,admin,seller'],
            'active' => ['required', 'boolean'],
        ]);

        if (! $request->user()->isSuperAdmin()) {
            if ($data['role'] === 'superadmin') {
                abort(403);
            }

            $data['tenant_setting_id'] = $request->user()->tenant_setting_id;
        } elseif (blank($data['tenant_setting_id'] ?? null)) {
            $data['tenant_setting_id'] = null;
        }

        if ($request->user()->isSuperAdmin() && $request->user()->is($user) && (! $data['active'] || $data['role'] !== 'superadmin')) {
            return response()->json([
                'message' => 'Voce nao pode remover seu proprio acesso de superadmin.',
            ], 422);
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        return response()->json($this->present($user->refresh()));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin() || $request->user()->isCompanyAdmin(), 403);

        if (! $request->user()->isSuperAdmin() && $user->tenant_setting_id !== $request->user()->tenant_setting_id) {
            abort(403);
        }

        if ($request->user()->is($user)) {
            return response()->json([
                'message' => 'Voce nao pode excluir seu proprio usuario.',
            ], 422);
        }

        $user->delete();

        return response()->json(status: 204);
    }

    private function present(User $user): array
    {
        return [
            ...$user->only(['id', 'tenant_setting_id', 'name', 'email', 'role', 'active', 'created_at', 'updated_at']),
            'tenant' => $user->tenant ? [
                'id' => $user->tenant->id,
                'name' => $user->tenant->name,
                'is_current' => $user->tenant->id === TenantSetting::current()->id,
            ] : null,
        ];
    }
}
