<?php

namespace App\Http\Controllers;

use App\Models\TenantSetting;
use App\Services\AsaasCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AsaasWebhookController extends Controller
{
    public function __invoke(Request $request, TenantSetting $tenant, AsaasCheckoutService $asaas): JsonResponse
    {
        $expectedToken = (string) data_get($tenant->payment_credentials, 'asaas.webhook_token');
        $receivedToken = (string) $request->header('asaas-access-token');

        if ($expectedToken === '' || ! hash_equals($expectedToken, $receivedToken)) {
            abort(401, 'Webhook Asaas nao autorizado.');
        }

        $payment = $asaas->syncWebhookPayment($tenant, (array) $request->input('payment', []));

        return response()->json([
            'received' => true,
            'linked' => (bool) $payment,
        ]);
    }
}
