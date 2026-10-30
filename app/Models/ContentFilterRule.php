<?php

namespace App\Models;

use Database\Factories\ContentFilterRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentFilterRule extends Model
{
    /** @use HasFactory<ContentFilterRuleFactory> */
    use HasFactory;

    protected $fillable = ['phrase', 'category', 'action', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
