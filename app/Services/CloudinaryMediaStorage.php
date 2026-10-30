<?php

namespace App\Services;

use App\Models\GeneratedImage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class CloudinaryMediaStorage
{
    /**
     * @return array{public_id: string, secure_url: string, bytes: int}
     */
    public function uploadPng(string $filePath, string $publicId, string $deliveryType = 'upload'): array
    {
        $credentials = $this->credentials();
        $contents = file_get_contents($filePath);
        if ($contents === false) {
            throw new RuntimeException('File PNG hasil render tidak dapat dibaca.');
        }

        try {
            $response = Http::connectTimeout(5)
                ->timeout(60)
                ->withBasicAuth($credentials['api_key'], $credentials['api_secret'])
                ->attach('file', $contents, basename($publicId).'.png', ['Content-Type' => 'image/png'])
                ->post($this->endpoint($credentials['cloud_name'], 'upload'), [
                    'public_id' => $publicId,
                    'overwrite' => 'true',
                    'type' => $deliveryType,
                ]);
            $response->throw();
        } catch (Throwable $exception) {
            throw new RuntimeException('Cloudinary menolak unggahan gambar.', previous: $exception);
        }

        $asset = $response->json();
        if (! is_array($asset)) {
            throw new RuntimeException('Cloudinary mengembalikan respons yang tidak valid.');
        }
        $secureUrl = $asset['secure_url'] ?? null;
        $returnedPublicId = $asset['public_id'] ?? null;

        if (! is_string($secureUrl) || ! is_string($returnedPublicId) || ! $this->isCloudinaryUrl($secureUrl, $credentials['cloud_name'])) {
            throw new RuntimeException('Cloudinary tidak mengembalikan URL gambar HTTPS yang valid.');
        }

        return [
            'public_id' => $returnedPublicId,
            'secure_url' => $secureUrl,
            'bytes' => (int) ($asset['bytes'] ?? strlen($contents)),
        ];
    }

    public function delete(string $publicId, string $deliveryType = 'upload'): void
    {
        $credentials = $this->credentials();

        try {
            $response = Http::connectTimeout(5)
                ->timeout(30)
                ->withBasicAuth($credentials['api_key'], $credentials['api_secret'])
                ->post($this->endpoint($credentials['cloud_name'], 'destroy'), [
                    'public_id' => $publicId,
                    'type' => $deliveryType,
                    'invalidate' => 'true',
                ]);
            $response->throw();
        } catch (Throwable $exception) {
            throw new RuntimeException('Cloudinary gagal menghapus gambar.', previous: $exception);
        }
    }

    public function url(GeneratedImage $image): string
    {
        if ($image->disk === 'cloudinary') {
            $url = ($image->metadata ?? [])['secure_url'] ?? null;
            if (! is_string($url) || ! $this->isCloudinaryUrl($url, $this->credentials()['cloud_name'])) {
                throw new RuntimeException('URL gambar Cloudinary tidak valid.');
            }

            return $url;
        }

        return Storage::disk($image->disk)->url($image->path);
    }

    public function contents(GeneratedImage $image): string
    {
        if ($image->disk !== 'cloudinary') {
            return Storage::disk($image->disk)->get($image->path);
        }

        $url = $this->url($image);
        try {
            $response = Http::connectTimeout(5)->timeout(30)->get($url);
            $response->throw();
        } catch (Throwable $exception) {
            throw new RuntimeException('Gambar Cloudinary tidak dapat diunduh.', previous: $exception);
        }

        if (! str_starts_with((string) $response->header('Content-Type'), 'image/')) {
            throw new RuntimeException('Cloudinary mengembalikan konten yang bukan gambar.');
        }

        return $response->body();
    }

    /**
     * @return array{cloud_name: string, api_key: string, api_secret: string}
     */
    private function credentials(): array
    {
        $cloudName = trim((string) config('services.cloudinary.cloud_name'));
        $apiKey = trim((string) config('services.cloudinary.api_key'));
        $apiSecret = trim((string) config('services.cloudinary.api_secret'));

        if ($cloudName === '' || $apiKey === '' || $apiSecret === '') {
            throw new RuntimeException('Konfigurasi Cloudinary belum lengkap.');
        }

        if (! preg_match('/^[A-Za-z0-9_-]+$/', $cloudName)) {
            throw new RuntimeException('Nama cloud Cloudinary tidak valid.');
        }

        return ['cloud_name' => $cloudName, 'api_key' => $apiKey, 'api_secret' => $apiSecret];
    }

    private function endpoint(string $cloudName, string $action): string
    {
        return 'https://api.cloudinary.com/v1_1/'.$cloudName.'/image/'.$action;
    }

    private function isCloudinaryUrl(string $url, string $cloudName): bool
    {
        $parsedUrl = parse_url($url);

        return ($parsedUrl['scheme'] ?? null) === 'https'
            && ($parsedUrl['host'] ?? null) === 'res.cloudinary.com'
            && str_starts_with($parsedUrl['path'] ?? '', '/'.$cloudName.'/image/');
    }
}
