<?php

namespace App\Services;

use App\Models\ActivePaymentAmount;
use App\Models\PaymentOrder;
use Illuminate\Database\QueryException;
use RuntimeException;

class UniquePaymentAmountService
{
    /**
     * Allocate a unique payment amount for an order to guarantee reliable identification.
     *
     * @return array{base_amount: int, unique_code: int, final_amount: int}
     */
    public function allocate(int $baseAmount, string $orderId, int $durationMinutes = 30): array
    {
        $this->cleanupExpired($durationMinutes);
        $minimumAmount = max(1, (int) config('qris.min_transaction_amount_idr', 100));
        $maximumAmount = max($minimumAmount, (int) config('qris.max_transaction_amount_idr', 2000000));

        if ($baseAmount < $minimumAmount || $baseAmount > $maximumAmount) {
            throw new RuntimeException('Nominal paket berada di luar batas transaksi QRIS yang dikonfigurasi.');
        }

        if (! config('qris.enable_unique_amount', true)) {
            return [
                'base_amount' => $baseAmount,
                'unique_code' => 0,
                'final_amount' => $baseAmount,
            ];
        }

        $min = max(1, (int) config('qris.min_unique_code', 1));
        $configuredMax = max($min, (int) config('qris.max_unique_code', 999));
        $max = min($configuredMax, $maximumAmount - $baseAmount);

        if ($max < $min) {
            throw new RuntimeException('Nominal paket tidak menyisakan rentang yang cukup untuk kode unik QRIS.');
        }

        $maxAttempts = 25;
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $usedAmounts = array_flip(array_merge(
                ActivePaymentAmount::pluck('amount_idr')->all(),
                PaymentOrder::where('status', 'pending')
                    ->where('created_at', '>=', now()->subMinutes($durationMinutes))
                    ->pluck('amount_idr')
                    ->all()
            ));

            $candidateCode = random_int($min, $max);
            $finalAmount = $baseAmount + $candidateCode;

            // If random code collides with an active amount, find first free sequential code
            if (isset($usedAmounts[$finalAmount])) {
                $found = false;
                for ($code = $min; $code <= $max; $code++) {
                    $testAmount = $baseAmount + $code;
                    if (! isset($usedAmounts[$testAmount])) {
                        $candidateCode = $code;
                        $finalAmount = $testAmount;
                        $found = true;
                        break;
                    }
                }

                if (! $found) {
                    throw new RuntimeException('Semua kode unik pembayaran untuk nominal ini sedang digunakan. Silakan tunggu beberapa saat.');
                }
            }

            try {
                ActivePaymentAmount::create([
                    'amount_idr' => $finalAmount,
                    'order_id' => $orderId,
                    'expires_at' => now()->addMinutes($durationMinutes),
                ]);

                return [
                    'base_amount' => $baseAmount,
                    'unique_code' => $candidateCode,
                    'final_amount' => $finalAmount,
                ];
            } catch (QueryException) {
                // Concurrency collision on amount_idr UNIQUE constraint, retry next attempt
                continue;
            }
        }

        throw new RuntimeException('Gagal mengalokasikan kode unik pembayaran. Silakan coba kembali.');
    }

    /**
     * Release a reserved amount so it can be re-used.
     */
    public function release(int $amountIdr): void
    {
        ActivePaymentAmount::where('amount_idr', $amountIdr)->delete();
    }

    /**
     * Release reservation by order id.
     */
    public function releaseForOrder(string $orderId): void
    {
        ActivePaymentAmount::where('order_id', $orderId)->delete();
    }

    /**
     * Clean up expired reservations and mark overdue orders as expired.
     */
    public function cleanupExpired(int $durationMinutes = 30): void
    {
        ActivePaymentAmount::where('expires_at', '<', now())->delete();

        PaymentOrder::where('status', 'pending')
            ->where(function ($query) use ($durationMinutes) {
                $query->where('qris_request_date', '<', now()->subMinutes($durationMinutes))
                    ->orWhere(function ($q) use ($durationMinutes) {
                        $q->whereNull('qris_request_date')
                            ->where('created_at', '<', now()->subMinutes($durationMinutes));
                    });
            })
            ->update(['status' => 'expired']);
    }
}
