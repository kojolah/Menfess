<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Menfess;
use App\Models\Template;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class PlaywrightMenfessImageRenderer
{
    public function __construct(
        private readonly CloudinaryMediaStorage $mediaStorage,
        private readonly MenfessPublicDomainResolver $domainResolver,
    ) {}

    /** @var array<string, array{width: int, height: int}> */
    private const FORMATS = [
        'instagram_portrait' => ['width' => 1080, 'height' => 1350],
        'instagram_square' => ['width' => 1080, 'height' => 1080],
        'x_landscape' => ['width' => 1200, 'height' => 675],
    ];

    public function preview(string $body, Category $category, string $format, ?string $photoPath = null, ?string $publicNumber = null, string $photoDisk = 'local', ?array $theme = null): string
    {
        $dimensions = self::FORMATS[$format] ?? throw new RuntimeException('Format gambar tidak didukung.');
        $page = $this->paginateText($body)[0];
        $temporaryPath = $this->renderToTemporaryPath($page, $category, $dimensions, $photoPath, $publicNumber, $theme, $photoDisk);
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
        if (! in_array($menfess->status, ['approved', 'published'], true)) {
            throw new RuntimeException('Gambar final hanya dapat dibuat untuk menfess yang disetujui.');
        }

        $dimensions = self::FORMATS[$format] ?? throw new RuntimeException('Format gambar tidak didukung.');
        $platform = str_starts_with($format, 'x_') ? 'x' : 'instagram';
        $attachment = $menfess->attachments()->first();
        $pages = $this->paginateText($menfess->body);
        $temporaryPaths = [];
        $uploadedAssets = [];
        $rows = [];
        $theme = array_merge($template?->theme ?? [], [
            'background' => $menfess->metadata['canvas_background'] ?? config('menfess.canvas_background'),
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
        $photoUri = null;
        if ($photoPath) {
            $photoBytes = Storage::disk($photoDisk)->get($photoPath);
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($photoBytes);
            if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                throw new RuntimeException('Format gambar tidak didukung untuk generator.');
            }
            $photoUri = 'data:'.$mime.';base64,'.base64_encode($photoBytes);
        }

        $temporaryOutputPath = tempnam(sys_get_temp_dir(), 'menfess-render-');
        if ($temporaryOutputPath === false) {
            throw new RuntimeException('Direktori sementara render tidak tersedia.');
        }

        $payload = [
            ...$dimensions,
            'output_path' => $temporaryOutputPath,
            'body' => $body,
            'category_name' => $category->name,
            'category_color' => $category->badge_color,
            'public_number' => $publicNumber,
            'photo_data_uri' => $photoUri,
            'brand_name' => $theme['brand_name'] ?? config('menfess.brand_name'),
            'username' => $theme['username'] ?? config('menfess.social_username'),
            'domain' => $this->domainResolver->resolve($theme),
            'date_label' => now(config('app.timezone', 'Asia/Jakarta'))->locale('id')->translatedFormat('d F Y · H:i'),
            'theme' => [
                'background' => $theme['background'] ?? config('menfess.canvas_background'),
                'accent' => $theme['accent'] ?? config('menfess.canvas_accent'),
            ],
        ];

        try {
            $process = new Process([config('menfess.node_binary', 'node'), base_path('scripts/render-menfess.mjs')]);
            $process->setInput(json_encode($payload, JSON_THROW_ON_ERROR));
            $process->setTimeout(60);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('PNG tidak dapat dibuat.');
            }

            return $temporaryOutputPath;
        } catch (Throwable $exception) {
            @unlink($temporaryOutputPath);

            throw $exception;
        }
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
