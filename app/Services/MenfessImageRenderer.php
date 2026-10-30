<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Menfess;
use App\Models\Template;

/**
 * Stable entry point shared by Livewire previews, generated-image downloads, and publication jobs.
 */
class MenfessImageRenderer
{
    public function __construct(
        private readonly ImagickMenfessImageRenderer $imagickRenderer,
        private readonly PlaywrightMenfessImageRenderer $playwrightRenderer,
    ) {}

    public function preview(string $body, Category $category, string $format, ?string $photoPath = null, ?string $publicNumber = null, string $photoDisk = 'local', ?array $theme = null): string
    {
        return $this->renderer()->preview($body, $category, $format, $photoPath, $publicNumber, $photoDisk, $theme);
    }

    /** @return array<int, array<string, mixed>> */
    public function persistFor(Menfess $menfess, string $format, ?Template $template = null): array
    {
        return $this->renderer()->persistFor($menfess, $format, $template);
    }

    private function renderer(): ImagickMenfessImageRenderer|PlaywrightMenfessImageRenderer
    {
        return match (config('menfess.image_renderer', 'playwright')) {
            'imagick' => $this->imagickRenderer,
            'playwright' => $this->playwrightRenderer,
            default => throw new \RuntimeException('IMAGE_RENDERER harus bernilai imagick atau playwright.'),
        };
    }
}
