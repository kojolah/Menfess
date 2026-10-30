<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicationJob extends Model
{
    protected $fillable = ['menfess_id', 'platform', 'status', 'scheduled_at', 'started_at', 'published_at', 'external_post_id', 'safe_error', 'tokens_reserved', 'idempotency_key'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'started_at' => 'datetime', 'published_at' => 'datetime'];
    }

    public function menfess(): BelongsTo
    {
        return $this->belongsTo(Menfess::class);
    }
}
