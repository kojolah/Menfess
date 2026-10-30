<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TopupPackage extends Model
{
    protected $fillable = ['name', 'price_idr', 'tokens', 'is_active'];

    protected function casts(): array
    {
        return ['price_idr' => 'integer', 'tokens' => 'integer', 'is_active' => 'boolean'];
    }
}
