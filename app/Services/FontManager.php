<?php

namespace App\Services;

use RuntimeException;

class FontManager
{
    public function font(string $weight = 'regular'): string
    {
        $configuredPath = config('menfess.image_fonts.'.$weight);
        $fontName = match ($weight) {
            'bold' => 'Inter-Bold',
            'serif' => 'Inter-Regular',
            default => 'Inter-Regular',
        };
        $candidates = array_filter([
            is_string($configuredPath) ? $configuredPath : null,
            resource_path('fonts/'.$fontName.'.ttf'),
            match ($weight) {
                'bold' => '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                'serif' => '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                default => '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            },
            match ($weight) {
                'bold' => '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
                'serif' => '/System/Library/Fonts/Supplemental/Arial.ttf',
                default => '/System/Library/Fonts/Supplemental/Arial.ttf',
            },
        ]);

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Font renderer tidak ditemukan. Pasang font Inter pada resources/fonts atau atur MENFESS_FONT_REGULAR dan MENFESS_FONT_BOLD.');
    }
}
