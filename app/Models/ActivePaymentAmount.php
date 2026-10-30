<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivePaymentAmount extends Model
{
    protected $fillable = [
        'amount_idr',
        'order_id',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_idr' => 'integer',
            'expires_at' => 'datetime',
        ];
    }
}
