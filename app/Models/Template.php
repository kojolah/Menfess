<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Template extends Model
{
    protected $fillable = ['name', 'kind', 'platform', 'width', 'height', 'theme', 'version', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['width' => 'integer', 'height' => 'integer', 'theme' => 'array', 'version' => 'integer', 'is_active' => 'boolean'];
    }

    public function generatedImages(): HasMany
    {
        return $this->hasMany(GeneratedImage::class);
    }
}
