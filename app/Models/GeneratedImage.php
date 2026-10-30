<?php

namespace App\Models;

use App\Services\CloudinaryMediaStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedImage extends Model
{
    protected $fillable = ['template_id', 'platform', 'width', 'height', 'disk', 'path', 'mime_type', 'metadata'];

    protected function casts(): array
    {
        return ['width' => 'integer', 'height' => 'integer', 'metadata' => 'array'];
    }

    public function getDeliveryUrlAttribute(): string
    {
        return app(CloudinaryMediaStorage::class)->url($this);
    }

    public function menfess(): BelongsTo
    {
        return $this->belongsTo(Menfess::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
