<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModerationAction extends Model
{
    protected $fillable = ['moderator_id', 'action', 'reason'];

    public function menfess(): BelongsTo
    {
        return $this->belongsTo(Menfess::class);
    }
}
