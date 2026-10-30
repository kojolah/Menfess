<?php

namespace App\Services;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WalletService
{
    public function adjust(
        int $walletId,
        int $amount,
        string $type,
        string $idempotencyKey,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $reason = null,
        ?int $adminId = null,
    ): WalletTransaction {
        if ($amount === 0 || $idempotencyKey === '') {
            throw new RuntimeException('Mutasi saldo harus memiliki jumlah dan kunci idempotensi.');
        }

        return DB::transaction(function () use ($walletId, $amount, $type, $idempotencyKey, $referenceType, $referenceId, $reason, $adminId): WalletTransaction {
            $existing = WalletTransaction::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            $wallet = Wallet::whereKey($walletId)->lockForUpdate()->firstOrFail();
            $before = (int) $wallet->balance;
            $after = $before + $amount;

            if ($after < 0) {
                throw new RuntimeException('Saldo token tidak mencukupi.');
            }

            $wallet->update(['balance' => $after]);

            return $wallet->transactions()->create([
                'type' => $type,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'idempotency_key' => $idempotencyKey,
                'reason' => $reason,
                'admin_id' => $adminId,
            ]);
        }, attempts: 3);
    }
}
