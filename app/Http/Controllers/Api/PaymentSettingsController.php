<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentSettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        abort_if($request->user()->role === 'seller', 403);

        $settings = PaymentSetting::mercadoPago();

        return response()->json([
            'provider' => $settings->provider,
            'public_key' => $settings->public_key,
            'sandbox' => $settings->sandbox,
            'statement_descriptor' => $settings->statement_descriptor,
            'configured' => $settings->configured(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        abort_if($request->user()->role === 'seller', 403);

        $data = $request->validate([
            'access_token' => ['nullable', 'string', 'max:2000'],
            'public_key' => ['nullable', 'string', 'max:255'],
            'sandbox' => ['required', 'boolean'],
            'statement_descriptor' => ['required', 'string', 'max:22'],
        ]);

        $settings = PaymentSetting::mercadoPago();
        $settings->fill([
            'public_key' => $data['public_key'] ?? null,
            'sandbox' => $data['sandbox'],
            'statement_descriptor' => $data['statement_descriptor'],
        ]);

        if (filled($data['access_token'] ?? null)) {
            $settings->access_token = $data['access_token'];
        }

        $settings->save();

        return $this->show();
    }
}
