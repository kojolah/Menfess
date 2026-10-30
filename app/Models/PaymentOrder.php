<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentOrder extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'topup_package_id',
        'base_amount_idr',
        'unique_code',
        'amount_idr',
        'tokens',
        'status',
        'payment_provider',
        'snap_token',
        'midtrans_transaction_id',
        'qris_invoice_id',
        'qris_content',
        'qris_nmid',
        'qris_request_date',
        'qris_paid_date',
        'qris_payment_methodby',
        'qris_customer_name',
        'last_checked_at',
        'paid_at',
        'provider_payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_amount_idr' => 'integer',
            'unique_code' => 'integer',
            'amount_idr' => 'integer',
            'tokens' => 'integer',
            'paid_at' => 'datetime',
            'qris_request_date' => 'datetime',
            'qris_paid_date' => 'datetime',
            'last_checked_at' => 'datetime',
            'provider_payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(TopupPackage::class, 'topup_package_id');
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function expiresAt(): ?Carbon
    {
        if (! $this->qris_request_date) {
            return $this->created_at ? $this->created_at->copy()->addMinutes(30) : null;
        }

        return $this->qris_request_date->copy()->addMinutes(30);
    }

    public function isExpired(): bool
    {
        if ($this->status === 'expired') {
            return true;
        }

        if ($this->status !== 'pending') {
            return false;
        }

        $expiry = $this->expiresAt();

        return $expiry !== null && $expiry->isPast();
    }
}
