<?php

namespace App\Http\Controllers;

use App\Models\PaymentOrder;
use App\Models\Wallet;
use App\Services\QrisInteractiveGateway;
use App\Services\UniquePaymentAmountService;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QrisCallbackController extends Controller
{
    public function __invoke(
        Request $request,
        QrisInteractiveGateway $gateway,
        WalletService $walletService,
        UniquePaymentAmountService $uniqueAmountService,
    ): JsonResponse {
        $payload = $request->validate([
            'invoice' => ['required', 'string', 'max:100'],
            'qris_status' => ['required', 'string', 'max:50'],
            'amount' => ['nullable', 'numeric'],
            'gross_amount' => ['nullable', 'numeric'],
            'trxvalue' => ['nullable', 'numeric'],
            'nominal' => ['nullable', 'numeric'],
            'rrn' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
            'qris_paid_date' => ['nullable', 'string', 'max:50'],
            'qris_payment_methodby' => ['nullable', 'string', 'max:100'],
            'qris_payment_customername' => ['nullable', 'string', 'max:150'],
        ]);

        $order = PaymentOrder::where('qris_invoice_id', $payload['invoice'])->first();

        if (! $order) {
            Log::warning('QRIS Webhook received for unknown invoice.', ['invoice' => $payload['invoice']]);

            return response()->json([
                'status' => 'error',
                'message' => 'Invoice tidak ditemukan.',
            ], 404);
        }

        // Validate amount if present in notification payload
        $incomingAmount = $payload['amount']
            ?? $payload['gross_amount']
            ?? $payload['trxvalue']
            ?? $payload['nominal']
            ?? null;

        if ($incomingAmount !== null && (! preg_match('/^\d+$/', (string) $incomingAmount) || (int) $incomingAmount !== (int) $order->amount_idr)) {
            Log::warning('QRIS Webhook payment amount mismatch.', [
                'order_id' => $order->order_id,
                'expected_amount' => $order->amount_idr,
                'received_amount' => $incomingAmount,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Nominal pembayaran tidak cocok dengan tagihan invoice.',
            ], 422);
        }

        if ($order->status === 'paid') {
            return response()->json(['status' => 'ok', 'message' => 'Pembayaran telah diproses sebelumnya.']);
        }

        if ($order->status !== 'pending' || $order->isExpired()) {
            if ($order->status === 'pending') {
                $order->update(['status' => 'expired']);
            }
            $uniqueAmountService->releaseForOrder($order->order_id);

            Log::warning('QRIS Webhook ignored for expired or cancelled order.', [
                'order_id' => $order->order_id,
                'status' => $order->status,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Invoice sudah kedaluwarsa atau dibatalkan.',
            ], 400);
        }

        if (! in_array(strtolower($payload['qris_status']), ['success', 'paid'], true)) {
            return response()->json(['status' => 'ok', 'message' => 'Notifikasi diterima; pembayaran belum berstatus lunas.']);
        }

        try {
            $paidDates = $this->paymentDates($payload, $order);
            $verification = null;
            foreach ($paidDates as $date) {
                $verification = $gateway->checkStatus(
                    invid: (string) $order->qris_invoice_id,
                    trxvalue: (int) $order->amount_idr,
                    trxdate: $date,
                );

                if ($verification['is_paid']) {
                    break;
                }
            }
        } catch (\Throwable $exception) {
            Log::warning('QRIS callback provider verification failed.', [
                'order_id' => $order->order_id,
                'exception' => $exception::class,
            ]);

            return response()->json(['status' => 'error', 'message' => 'Verifikasi pembayaran sementara tidak tersedia.'], 503);
        }

        if (! ($verification['is_paid'] ?? false)) {
            Log::warning('QRIS callback did not match a paid provider invoice.', ['order_id' => $order->order_id]);

            return response()->json(['status' => 'ok', 'message' => 'Pembayaran belum dapat diverifikasi oleh provider.']);
        }

        DB::transaction(function () use ($order, $verification, $walletService, $uniqueAmountService): void {
            $locked = PaymentOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending' || $locked->isExpired()) {
                if ($locked->status === 'pending') {
                    $locked->update(['status' => 'expired']);
                    $uniqueAmountService->releaseForOrder($locked->order_id);
                }

                return;
            }

            $paidAt = now('Asia/Jakarta');

            $locked->update([
                'status' => 'paid',
                'paid_at' => $paidAt,
                'qris_paid_date' => $paidAt,
                'qris_payment_methodby' => $verification['payment_method'] ?? $locked->qris_payment_methodby,
                'qris_customer_name' => $verification['customer_name'] ?? $locked->qris_customer_name,
                'provider_payload' => $verification['raw'],
            ]);

            $uniqueAmountService->releaseForOrder($locked->order_id);

            $wallet = Wallet::firstOrCreate(['user_id' => $locked->user_id]);
            $walletService->adjust(
                $wallet->id,
                $locked->tokens,
                'topup',
                'payment:'.$locked->order_id,
                PaymentOrder::class,
                $locked->id,
                'Top-up terverifikasi QRIS InterActive',
            );
        }, attempts: 3);

        return response()->json([
            'status' => 'ok',
            'message' => 'Notifikasi QRIS berhasil diproses.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function paymentDates(array $payload, PaymentOrder $order): array
    {
        $dates = [];

        if (! empty($payload['qris_paid_date'])) {
            $dates[] = Carbon::parse($payload['qris_paid_date'], 'Asia/Jakarta')->format('Y-m-d');
        }

        if ($order->qris_request_date) {
            $dates[] = $order->qris_request_date->copy()->timezone('Asia/Jakarta')->format('Y-m-d');
        }

        $dates[] = now('Asia/Jakarta')->format('Y-m-d');

        return array_values(array_unique($dates));
    }
}
