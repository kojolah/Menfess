<?php

namespace App\Services;

use Imagick;
use ImagickDraw;

class MenfessTextLayout
{
    /** @return array{font_size: int, lines: array<int, string>, line_height: float, height: float} */
    public function fit(Imagick $image, string $text, string $font, int $maximumWidth, int $maximumHeight, int $startingSize, int $minimumSize, float $lineHeight = 1.42): array
    {
        for ($fontSize = $startingSize; $fontSize >= $minimumSize; $fontSize--) {
            $draw = $this->drawSettings($font, $fontSize);
            $lines = $this->wrap($image, $draw, $text, $maximumWidth);
            $rowHeight = $fontSize * $lineHeight;
            if (count($lines) * $rowHeight <= $maximumHeight) {
                return [
                    'font_size' => $fontSize,
                    'lines' => $lines,
                    'line_height' => $rowHeight,
                    'height' => count($lines) * $rowHeight,
                ];
            }
        }

        $draw = $this->drawSettings($font, $minimumSize);
        $lines = $this->wrap($image, $draw, $text, $maximumWidth);

        return [
            'font_size' => $minimumSize,
            'lines' => $lines,
            'line_height' => $minimumSize * $lineHeight,
            'height' => count($lines) * $minimumSize * $lineHeight,
        ];
    }

    /** @param array<int, string> $lines */
    public function draw(Imagick $image, array $lines, string $font, int $fontSize, string $color, int $x, int $top, float $lineHeight): void
    {
        $draw = $this->drawSettings($font, $fontSize);
        $draw->setFillColor($color);
        $metrics = $image->queryFontMetrics($draw, 'Ag');
        $baselineOffset = (float) ($metrics['ascender'] ?? $fontSize);

        foreach ($lines as $index => $line) {
            $image->annotateImage($draw, $x, (int) round($top + $baselineOffset + ($index * $lineHeight)), 0, $line);
        }
    }

    private function drawSettings(string $font, int $fontSize): ImagickDraw
    {
        $draw = new ImagickDraw;
        $draw->setFont($font);
        $draw->setFontSize($fontSize);
        $draw->setTextAntialias(true);
        $draw->setTextEncoding('UTF-8');

        return $draw;
    }

    /** @return array<int, string> */
    private function wrap(Imagick $image, ImagickDraw $draw, string $text, int $maximumWidth): array
    {
        $lines = [];
        $paragraphs = preg_split('/\R/u', $text) ?: [''];

        foreach ($paragraphs as $paragraph) {
            $words = preg_split('/\s+/u', trim($paragraph), -1, PREG_SPLIT_NO_EMPTY) ?: [''];
            $line = '';

            foreach ($words as $word) {
                $candidate = $line === '' ? $word : $line.' '.$word;
                if ($line !== '' && $this->textWidth($image, $draw, $candidate) > $maximumWidth) {
                    $lines[] = $line;
                    $line = '';
                }

                if ($this->textWidth($image, $draw, $word) > $maximumWidth) {
                    $characters = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [$word];
                    foreach ($characters as $character) {
                        $candidate = $line.$character;
                        if ($line !== '' && $this->textWidth($image, $draw, $candidate) > $maximumWidth) {
                            $lines[] = $line;
                            $line = '';
                        }
                        $line .= $character;
                    }

                    continue;
                }

                $line = $line === '' ? $word : $line.' '.$word;
            }

            $lines[] = $line;
        }

        return $lines ?: [''];
    }

    private function textWidth(Imagick $image, ImagickDraw $draw, string $text): float
    {
        return (float) ($image->queryFontMetrics($draw, $text)['textWidth'] ?? 0);
    }
}
