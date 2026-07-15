<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = \App\Models\User::where('email', $credentials['email'])->first();

        if (
            ! $user
            || ! Hash::check($credentials['password'], $user->password)
            || ! $user->active
            || ($user->tenant && ! $user->tenant->active)
        ) {
            throw ValidationException::withMessages([
                'email' => 'Credenciais invalidas.',
            ]);
        }

        Auth::login($user, (bool) $request->boolean('remember'));

        $request->session()->regenerate();

        return response()->json([
            'user' => [
                ...$request->user()->only(['id', 'tenant_setting_id', 'name', 'email', 'role']),
                'tenant' => $request->user()->tenant ? [
                    'id' => $request->user()->tenant->id,
                    'name' => $request->user()->tenant->name,
                ] : null,
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }
}
