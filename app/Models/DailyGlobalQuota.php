<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyGlobalQuota extends Model
{
    protected $fillable = [
        'date',
        'published_free_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'string',
            'published_free_count' => 'integer',
        ];
    }
}
