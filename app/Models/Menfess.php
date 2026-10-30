<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menfess extends Model
{
    protected $fillable = ['user_id', 'category_id', 'body', 'censored_count', 'filter_status', 'filtered_at', 'platforms', 'status', 'submitted_at', 'public_number', 'reviewed_at', 'reviewed_by', 'rejection_reason', 'metadata'];

    protected function casts(): array
    {
        return ['platforms' => 'array', 'metadata' => 'array', 'censored_count' => 'integer', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'filtered_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MenfessAttachment::class);
    }

    public function generatedImages(): HasMany
    {
        return $this->hasMany(GeneratedImage::class);
    }

    public function publicationJobs(): HasMany
    {
        return $this->hasMany(PublicationJob::class);
    }

    public function moderationActions(): HasMany
    {
        return $this->hasMany(ModerationAction::class);
    }
}
