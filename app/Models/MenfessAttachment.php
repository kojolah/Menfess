<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenfessAttachment extends Model
{
    protected $fillable = ['disk', 'path', 'mime_type', 'size_bytes'];

    public function menfess(): BelongsTo
    {
        return $this->belongsTo(Menfess::class);
    }
}
