<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicationUsage extends Model
{
    protected $table = 'publication_usage';

    protected $fillable = ['user_id', 'publication_job_id', 'platform', 'is_free', 'tokens_charged', 'published_at'];

    protected function casts(): array
    {
        return ['is_free' => 'boolean', 'published_at' => 'datetime', 'tokens_charged' => 'integer'];
    }

    public function publicationJob(): BelongsTo
    {
        return $this->belongsTo(PublicationJob::class);
    }
}
