<?php

namespace App\Models;

use Database\Factories\ContentFilterExceptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentFilterException extends Model
{
    /** @use HasFactory<ContentFilterExceptionFactory> */
    use HasFactory;

    protected $fillable = ['phrase', 'reason', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
