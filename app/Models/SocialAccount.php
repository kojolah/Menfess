<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialAccount extends Model
{
    protected $fillable = ['platform', 'label', 'account_id', 'access_token', 'refresh_token', 'token_expires_at', 'settings', 'is_active'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
