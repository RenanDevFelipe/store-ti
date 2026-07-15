<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\SalesLink;
use App\Services\EvolutionNotificationService;
use App\Services\MercadoPagoCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MercadoPagoWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        MercadoPagoCheckoutService $checkout,
        EvolutionNotificationService $notifications
    ): JsonResponse
    {
        $paymentId = $request->input('data.id') ?? $request->input('id');

        if (! $paymentId) {
            return response()->json(['received' => true]);
        }

        $payment = $checkout->fetchPayment((string) $paymentId);
        $externalReference = isset($payment->external_reference) ? $payment->external_reference : null;
        $status = isset($payment->status) ? $payment->status : 'pending';
        $approvedAt = isset($payment->date_approved) ? $payment->date_approved : null;
        $effectiveStatus = $status === 'approved' && blank($approvedAt) ? 'pending' : $status;
        $salesLink = SalesLink::where('public_id', $externalReference)->first();

        if (! $salesLink) {
            return response()->json(['received' => true, 'linked' => false]);
        }

        $localPayment = Payment::updateOrCreate(
            ['mp_payment_id' => (string) $payment->id],
            [
                'sales_link_id' => $salesLink->id,
                'status' => $effectiveStatus,
                'status_detail' => isset($payment->status_detail) ? $payment->status_detail : null,
                'payment_method_id' => isset($payment->payment_method_id) ? $payment->payment_method_id : null,
                'payment_type_id' => isset($payment->payment_type_id) ? $payment->payment_type_id : null,
                'amount_cents' => (int) round((isset($payment->transaction_amount) ? $payment->transaction_amount : 0) * 100),
                'paid_at' => $effectiveStatus === 'approved' ? $approvedAt : null,
                'raw_payload' => json_decode(json_encode($payment), true),
            ]
        );

        $salesLink->update([
            'status' => match ($effectiveStatus) {
                'approved' => 'paid',
                'pending', 'in_process' => 'pending',
                'cancelled', 'refunded', 'charged_back' => 'cancelled',
                default => $salesLink->status,
            },
        ]);

        $notifications->notifyPaymentUpdated($localPayment->load('salesLink.product'));

        return response()->json(['received' => true, 'linked' => true]);
    }
}
