<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Menfess;
use App\Models\Template;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;
use ImagickDraw;
use ImagickPixel;
use RuntimeException;
use Throwable;

class ImagickMenfessImageRenderer
{
    /** @var array<string, array{width: int, height: int}> */
    private const FORMATS = [
        'instagram_portrait' => ['width' => 1080, 'height' => 1350],
        'instagram_square' => ['width' => 1080, 'height' => 1080],
        'x_landscape' => ['width' => 1200, 'height' => 675],
    ];

    public function __construct(
        private readonly CloudinaryMediaStorage $mediaStorage,
        private readonly FontManager $fontManager,
        private readonly MenfessTextLayout $textLayout,
    ) {}

    public function assertAvailable(): void
    {
        if (! extension_loaded('imagick') || ! class_exists(Imagick::class)) {
            throw new RuntimeException('PHP Imagick tidak tersedia. Aktifkan ekstensi Imagick untuk versi PHP yang digunakan aplikasi.');
        }

        $version = Imagick::getVersion();
        if (! preg_match('/ImageMagick\s+(\d+)\.(\d+)\.(\d+)/i', $version['versionString'] ?? '', $matches)) {
            throw new RuntimeException('Versi ImageMagick tidak dapat diverifikasi.');
        }

        $versionNumber = ((int) $matches[1] * 10000) + ((int) $matches[2] * 100) + (int) $matches[3];
        if ($versionNumber < 60503) {
            throw new RuntimeException('Imagick membutuhkan ImageMagick 6.5.3-10 atau lebih baru; gunakan rilis 7.x yang dipelihara bila tersedia.');
        }
    }

    public function jpegFromPng(string $pngBytes): string
    {
        $this->assertAvailable();
        $image = new Imagick;
        $flattened = null;

        try {
            $image->readImageBlob($pngBytes);
            $image->setImageBackgroundColor('#FFFFFF');
            $flattened = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            $flattened->setImageFormat('jpeg');
            $flattened->setImageCompression(Imagick::COMPRESSION_JPEG);
            $flattened->setImageCompressionQuality(max(1, min(100, (int) config('menfess.image_renderer_quality', 95))));

            return $flattened->getImageBlob();
        } catch (Throwable $exception) {
            throw new RuntimeException('Imagick tidak dapat menghasilkan JPEG dari gambar menfess.', previous: $exception);
        } finally {
            $image->clear();
            $image->destroy();
            if ($flattened instanceof Imagick) {
                $flattened->clear();
                $flattened->destroy();
            }
        }
    }

    /** @return array<string, int> */
    public static function dimensionsFor(string $format): array
    {
        return self::FORMATS[$format] ?? throw new RuntimeException('Format gambar tidak didukung.');
    }

    public function preview(string $body, Category $category, string $format, ?string $photoPath = null, ?string $publicNumber = null, string $photoDisk = 'local', ?array $theme = null): string
    {
        $this->assertAvailable();
        $dimensions = self::dimensionsFor($format);
        $temporaryPath = $this->renderToTemporaryPath($body, $category, $dimensions, $photoPath, $publicNumber, $theme, $photoDisk);
        $asset = null;

        try {
            $asset = $this->mediaStorage->uploadPng($temporaryPath, 'campus-menfess/previews/'.Str::uuid(), 'authenticated');
            $contents = file_get_contents($temporaryPath);
            if ($contents === false) {
                throw new RuntimeException('Preview PNG tidak dapat dibaca.');
            }

            return base64_encode($contents);
        } finally {
            @unlink($temporaryPath);
            if ($asset) {
                try {
                    $this->mediaStorage->delete($asset['public_id'], 'authenticated');
                } catch (Throwable $exception) {
                    Log::warning('Temporary Cloudinary preview cleanup failed.', [
                        'public_id' => $asset['public_id'],
                        'exception' => $exception::class,
                    ]);
                }
            }
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function persistFor(Menfess $menfess, string $format, ?Template $template = null): array
    {
        $this->assertAvailable();
        if (! in_array($menfess->status, ['approved', 'published'], true)) {
            throw new RuntimeException('Gambar final hanya dapat dibuat untuk menfess yang disetujui.');
        }

        $dimensions = self::dimensionsFor($format);
        $platform = str_starts_with($format, 'x_') ? 'x' : 'instagram';
        $attachment = $menfess->attachments()->first();
        $pages = $this->paginateText($menfess->body);
        $temporaryPaths = [];
        $uploadedAssets = [];
        $rows = [];
        $metadata = $menfess->metadata ?? [];
        $theme = array_merge($template?->theme ?? [], [
            'background' => $metadata['canvas_background'] ?? config('menfess.canvas_background'),
            'accent' => config('menfess.canvas_accent'),
        ]);

        try {
            foreach ($pages as $index => $body) {
                $number = $menfess->public_number.' · '.($index + 1).'/'.count($pages);
                $temporaryPath = $this->renderToTemporaryPath(
                    $body,
                    $menfess->category,
                    $dimensions,
                    $attachment?->path,
                    $number,
                    $theme,
                    $attachment?->disk ?? 'local',
                );
                $temporaryPaths[] = $temporaryPath;
                $asset = $this->mediaStorage->uploadPng(
                    $temporaryPath,
                    'campus-menfess/menfess/'.$menfess->id.'/'.$platform.'/'.Str::uuid(),
                );
                $uploadedAssets[] = $asset;
                $rows[] = [
                    'template_id' => $template?->id,
                    'platform' => $platform,
                    'width' => $dimensions['width'],
                    'height' => $dimensions['height'],
                    'disk' => 'cloudinary',
                    'path' => $asset['public_id'],
                    'mime_type' => 'image/png',
                    'metadata' => [
                        'format' => $format,
                        'template_version' => $template?->version ?? 1,
                        'slide' => $index + 1,
                        'slides' => count($pages),
                        'secure_url' => $asset['secure_url'],
                        'bytes' => $asset['bytes'],
                        'delivery_type' => 'upload',
                        'canvas_background' => $theme['background'],
                        'renderer' => 'imagick',
                    ],
                ];
            }

            return DB::transaction(function () use ($menfess, $rows): array {
                $images = [];
                foreach ($rows as $row) {
                    $images[] = $menfess->generatedImages()->create($row)->toArray();
                }

                return $images;
            });
        } catch (Throwable $exception) {
            foreach ($uploadedAssets as $asset) {
                try {
                    $this->mediaStorage->delete($asset['public_id']);
                } catch (Throwable $cleanupException) {
                    Log::warning('Cloudinary render cleanup failed after generation error.', [
                        'public_id' => $asset['public_id'],
                        'exception' => $cleanupException::class,
                    ]);
                }
            }

            throw $exception;
        } finally {
            foreach ($temporaryPaths as $temporaryPath) {
                @unlink($temporaryPath);
            }
        }
    }

    /** @param array{width: int, height: int} $dimensions */
    private function renderToTemporaryPath(string $body, Category $category, array $dimensions, ?string $photoPath, ?string $publicNumber, ?array $theme = null, string $photoDisk = 'local'): string
    {
        $this->assertAvailable();
        $background = $this->safeColor($theme['background'] ?? config('menfess.canvas_background'), '#F6DDE8');
        $accent = $this->safeColor(config('menfess.canvas_accent'), '#DCEBFA');
        $badgeColor = $this->safeColor($category->badge_color, '#DCEBFA');
        $regularFont = $this->fontManager->font('regular');
        $boldFont = $this->fontManager->font('bold');
        $serifFont = $this->fontManager->font('serif');
        $width = $dimensions['width'];
        $height = $dimensions['height'];
        $image = new Imagick;
        $image->newImage($width, $height, new ImagickPixel($background), 'png');
        $image->setImageColorspace(Imagick::COLORSPACE_SRGB);
        $image->setImageResolution(144, 144);
        $image->setOption('png:compression-level', '9');
        $image->setOption('png:compression-filter', '5');

        // The existing HTML template uses fixed pixel spacing for every canvas format.
        $scale = 1.0;
        $padding = (int) round(54 * $scale);
        $headerY = $padding;
        $logoSize = (int) round(64 * $scale);
        $brandX = $padding + $logoSize + (int) round(18 * $scale);
        $this->drawHeader($image, $boldFont, $regularFont, $padding, $headerY, $logoSize, $brandX, $theme);

        $footerY = $height - $padding - (int) round(20 * $scale);
        $cardX = $padding;
        $cardY = $headerY + $logoSize + (int) round(38 * $scale);
        $cardWidth = $width - ($padding * 2);
        $cardBottom = $footerY - (int) round(28 * $scale);
        $cardHeight = max(1, $cardBottom - $cardY);
        $cardRadius = (int) round(42 * $scale);
        $this->drawCard($image, $cardX, $cardY, $cardWidth, $cardHeight, $cardRadius);

        $cardInset = (int) round(48 * $scale);
        $contentX = $cardX + $cardInset;
        $contentWidth = $cardWidth - ($cardInset * 2);
        $badgeY = $cardY + (int) round(52 * $scale);
        $this->drawQuote($image, $serifFont, $accent, $cardX + (int) round(31 * $scale), $cardY + (int) round(18 * $scale), (int) round(100 * $scale));
        $badgeBottom = $this->drawBadge($image, $boldFont, $category->name, $badgeColor, $contentX, $badgeY, $scale);

        $photo = $this->readPhoto($photoDisk, $photoPath);
        $number = $publicNumber ?? 'PRATINJAU';
        $numberY = $cardY + $cardHeight - (int) round(52 * $scale);
        if ($photo) {
            $photoY = $badgeBottom + (int) round(30 * $scale);
            $photoHeight = (int) round($cardHeight * 0.5);
            $this->drawPhoto($image, $photo, $contentX, $photoY, $contentWidth, $photoHeight, (int) round(26 * $scale));
            $textTop = $photoY + $photoHeight + (int) round(26 * $scale);
            $maxTextHeight = max(1, $numberY - $textTop - (int) round(34 * $scale));
            $layout = $this->textLayout->fit($image, $body, $regularFont, $contentWidth, $maxTextHeight, (int) round(28 * $scale), (int) round(18 * $scale), 1.32);
            $this->textLayout->draw($image, $layout['lines'], $regularFont, $layout['font_size'], '#182A4B', $contentX, $textTop, $layout['line_height']);
        } else {
            $textTop = $badgeBottom + (int) round(36 * $scale);
            $maxTextHeight = max(1, $numberY - $textTop - (int) round(32 * $scale));
            $layout = $this->textLayout->fit($image, $body, $boldFont, $contentWidth, $maxTextHeight, (int) round(42 * $scale), (int) round(24 * $scale), 1.42);
            $this->textLayout->draw($image, $layout['lines'], $boldFont, $layout['font_size'], '#182A4B', $contentX, $textTop, $layout['line_height']);
        }

        $this->drawNumber($image, $boldFont, $number, $cardX + $cardInset, $numberY, $contentWidth);
        $this->drawFooter($image, $boldFont, $padding, $footerY, $width, $theme, $scale);

        $temporaryOutputPath = tempnam(sys_get_temp_dir(), 'menfess-imagick-');
        if ($temporaryOutputPath === false) {
            throw new RuntimeException('Direktori sementara render tidak tersedia.');
        }

        try {
            $image->setImageFormat('png');
            $image->writeImage($temporaryOutputPath);

            return $temporaryOutputPath;
        } catch (Throwable $exception) {
            @unlink($temporaryOutputPath);

            throw new RuntimeException('Imagick tidak dapat menyimpan PNG menfess.', previous: $exception);
        } finally {
            $image->clear();
            $image->destroy();
            $photo?->clear();
            $photo?->destroy();
        }
    }

    private function drawHeader(Imagick $image, string $boldFont, string $regularFont, int $x, int $y, int $logoSize, int $brandX, ?array $theme): void
    {
        $draw = new ImagickDraw;
        $draw->setFillColor('#FFFFFF');
        $draw->roundRectangle($x, $y, $x + $logoSize, $y + $logoSize, (int) ($logoSize * 0.34), (int) ($logoSize * 0.34));
        $image->drawImage($draw);
        $this->drawSpark($image, $x + (int) ($logoSize / 2), $y + (int) ($logoSize / 2), max(6, (int) ($logoSize * 0.2)));

        $brand = (string) ($theme['brand_name'] ?? config('menfess.brand_name'));
        $username = (string) ($theme['username'] ?? config('menfess.social_username'));
        $date = now(config('app.timezone', 'Asia/Jakarta'))->locale('id')->translatedFormat('d F Y · H:i');
        $this->annotate($image, $boldFont, 22, '#182A4B', $brandX, $y + 27, $brand);
        $this->annotate($image, $regularFont, 15, '#5D6A82', $brandX, $y + 55, $username.' · '.$date);
    }

    private function drawSpark(Imagick $image, int $centerX, int $centerY, int $arm): void
    {
        $draw = new ImagickDraw;
        $draw->setStrokeColor('#C96C91');
        $draw->setStrokeWidth(2.5);
        $draw->setStrokeLineCap(Imagick::LINECAP_ROUND);
        $draw->setFillColor('none');
        foreach ([0, 45, 90, 135] as $angle) {
            $radians = deg2rad($angle);
            $offsetX = (int) round(cos($radians) * $arm);
            $offsetY = (int) round(sin($radians) * $arm);
            $draw->line($centerX - $offsetX, $centerY - $offsetY, $centerX + $offsetX, $centerY + $offsetY);
        }
        $draw->circle($centerX, $centerY, $centerX + 2.5, $centerY);
        $image->drawImage($draw);
    }

    private function drawCard(Imagick $image, int $x, int $y, int $width, int $height, int $radius): void
    {
        $shadow = new Imagick;
        $shadow->newImage($image->getImageWidth(), $image->getImageHeight(), new ImagickPixel('transparent'), 'png');
        $shadowDraw = new ImagickDraw;
        $shadowDraw->setFillColor('rgba(24,42,75,0.07)');
        $shadowDraw->roundRectangle($x, $y + 9, $x + $width, $y + $height + 9, $radius, $radius);
        $shadow->drawImage($shadowDraw);
        $shadow->blurImage(0, 14);
        $image->compositeImage($shadow, Imagick::COMPOSITE_OVER, 0, 0);
        $shadow->clear();
        $shadow->destroy();

        $draw = new ImagickDraw;
        $draw->setFillColor('#FFFFFF');
        $draw->roundRectangle($x, $y, $x + $width, $y + $height, $radius, $radius);
        $image->drawImage($draw);
    }

    private function drawQuote(Imagick $image, string $font, string $accent, int $x, int $y, int $size): void
    {
        $draw = new ImagickDraw;
        $draw->setFont($font);
        $draw->setFontSize($size);
        $draw->setFillColor($this->rgba($accent, 0.85));
        $draw->setTextEncoding('UTF-8');
        $image->annotateImage($draw, $x, $y + $size, 0, '“');
    }

    private function drawBadge(Imagick $image, string $font, string $label, string $color, int $x, int $y, float $scale): int
    {
        $fontSize = (int) round(17 * $scale);
        $metricsDraw = new ImagickDraw;
        $metricsDraw->setFont($font);
        $metricsDraw->setFontSize($fontSize);
        $metricsDraw->setTextEncoding('UTF-8');
        $metrics = $image->queryFontMetrics($metricsDraw, $label);
        $horizontalPadding = (int) round(20 * $scale);
        $verticalPadding = (int) round(11 * $scale);
        $badgeHeight = (int) ceil($fontSize + ($verticalPadding * 2));
        $badgeWidth = (int) ceil(($metrics['textWidth'] ?? 0) + ($horizontalPadding * 2));
        $draw = new ImagickDraw;
        $draw->setFillColor($color);
        $draw->roundRectangle($x, $y, $x + $badgeWidth, $y + $badgeHeight, (int) ($badgeHeight / 2), (int) ($badgeHeight / 2));
        $image->drawImage($draw);
        $this->annotate($image, $font, $fontSize, '#182A4B', $x + $horizontalPadding, $y + $verticalPadding + $fontSize, $label);

        return $y + $badgeHeight;
    }

    private function readPhoto(string $disk, ?string $path): ?Imagick
    {
        if (! $path) {
            return null;
        }

        $bytes = Storage::disk($disk)->get($path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new RuntimeException('Format gambar tidak didukung untuk generator.');
        }

        $photo = new Imagick;
        $photo->readImageBlob($bytes);
        if (method_exists($photo, 'autoOrientImage')) {
            $photo->autoOrientImage();
        }
        $photo->setImageColorspace(Imagick::COLORSPACE_SRGB);

        return $photo;
    }

    private function drawPhoto(Imagick $canvas, Imagick $photo, int $x, int $y, int $width, int $height, int $radius): void
    {
        $photo->cropThumbnailImage($width, $height);
        $photo->setImagePage(0, 0, 0, 0);
        $mask = new Imagick;
        $mask->newImage($width, $height, new ImagickPixel('transparent'), 'png');
        $draw = new ImagickDraw;
        $draw->setFillColor('#FFFFFF');
        $draw->roundRectangle(0, 0, $width, $height, $radius, $radius);
        $mask->drawImage($draw);
        $photo->compositeImage($mask, Imagick::COMPOSITE_DSTIN, 0, 0);
        $canvas->compositeImage($photo, Imagick::COMPOSITE_OVER, $x, $y);
        $mask->clear();
        $mask->destroy();
    }

    private function drawNumber(Imagick $image, string $font, string $text, int $left, int $baseline, int $width): void
    {
        $fontSize = 16;
        $draw = new ImagickDraw;
        $draw->setFont($font);
        $draw->setFontSize($fontSize);
        $draw->setTextEncoding('UTF-8');
        $textWidth = (float) ($image->queryFontMetrics($draw, $text)['textWidth'] ?? 0);
        if ($textWidth > $width) {
            $fontSize = 13;
            $draw->setFontSize($fontSize);
            $textWidth = (float) ($image->queryFontMetrics($draw, $text)['textWidth'] ?? 0);
        }
        $draw->setFillColor('#8A94A5');
        $metrics = $image->queryFontMetrics($draw, 'Ag');
        $image->annotateImage($draw, (int) round($left + $width - $textWidth), $baseline, 0, $text);
    }

    private function drawFooter(Imagick $image, string $font, int $left, int $baseline, int $width, ?array $theme, float $scale): void
    {
        $fontSize = (int) round(16 * $scale);
        $domain = (string) ($theme['domain'] ?? config('app.url'));
        $username = (string) ($theme['username'] ?? config('menfess.social_username'));
        $this->annotate($image, $font, $fontSize, '#58657E', $left, $baseline, $domain);
        $draw = new ImagickDraw;
        $draw->setFont($font);
        $draw->setFontSize($fontSize);
        $draw->setTextEncoding('UTF-8');
        $draw->setFillColor('#182A4B');
        $textWidth = (float) ($image->queryFontMetrics($draw, $username)['textWidth'] ?? 0);
        $image->annotateImage($draw, (int) round($width - $left - $textWidth), $baseline, 0, $username);
    }

    private function annotate(Imagick $image, string $font, int $size, string $color, int $x, int $baseline, string $text): void
    {
        $draw = new ImagickDraw;
        $draw->setFont($font);
        $draw->setFontSize($size);
        $draw->setTextEncoding('UTF-8');
        $draw->setFillColor($color);
        $image->annotateImage($draw, $x, $baseline, 0, $text);
    }

    private function safeColor(mixed $color, string $fallback): string
    {
        return is_string($color) && preg_match('/^#[0-9A-Fa-f]{6}$/', $color) === 1 ? strtoupper($color) : $fallback;
    }

    private function rgba(string $hex, float $opacity): string
    {
        return sprintf('rgba(%d,%d,%d,%.2f)', hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2)), $opacity);
    }

    /** @return array<int, string> */
    private function paginateText(string $body): array
    {
        $units = preg_split('/(?<=[.!?])\s+/u', trim($body), -1, PREG_SPLIT_NO_EMPTY) ?: [trim($body)];
        $sentences = [];
        foreach ($units as $unit) {
            if (mb_strlen($unit) <= 360) {
                $sentences[] = $unit;

                continue;
            }
            $words = preg_split('/\s+/u', $unit, -1, PREG_SPLIT_NO_EMPTY) ?: [$unit];
            $chunk = '';
            foreach ($words as $word) {
                if ($chunk !== '' && mb_strlen($chunk.' '.$word) > 360) {
                    $sentences[] = $chunk;
                    $chunk = '';
                }
                $chunk = trim($chunk.' '.$word);
            }
            if ($chunk !== '') {
                $sentences[] = $chunk;
            }
        }

        $pages = [];
        $current = '';
        foreach ($sentences as $sentence) {
            if ($current !== '' && mb_strlen($current.' '.$sentence) > 360) {
                $pages[] = $current;
                $current = '';
            }
            $current = trim($current.' '.$sentence);
        }
        if ($current !== '') {
            $pages[] = $current;
        }

        return $pages ?: [''];
    }
}
