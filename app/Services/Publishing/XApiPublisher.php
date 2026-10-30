<?php

namespace App\Services\Publishing;

use App\Models\PublicationJob;
use App\Services\CloudinaryMediaStorage;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class XApiPublisher implements SocialPublisher
{
    private const MEDIA_UPLOAD_URL = 'https://api.x.com/2/media/upload';

    private const CREATE_POST_URL = 'https://api.x.com/2/tweets';

    public function __construct(
        private readonly CloudinaryMediaStorage $mediaStorage,
        private readonly XOAuth1a $oauth,
    ) {}

    public function publish(PublicationJob $publicationJob): PublicationResult
    {
        $credentials = $this->credentials();
        $menfess = $publicationJob->menfess;

        $hasAttachments = $menfess->attachments()->exists();
        $renderedImages = $menfess->generatedImages()->where('platform', 'x')->oldest()->get();
        $hasMedia = $hasAttachments || $renderedImages->isNotEmpty();

        // 1. Text-only menfess: If menfess has no media attachments, skip upload completely.
        if (! $hasMedia) {
            $postId = $this->createPost($menfess->body, [], $credentials, $publicationJob);

            return new PublicationResult($postId);
        }

        // 2. Menfess with media:
        // Validate media count (1 to 4 images supported on X).
        $mediaIds = [];
        if ($renderedImages->isNotEmpty()) {
            if ($renderedImages->count() > 4) {
                throw new PublicationNeedsReview('Publikasi X mendukung maksimal 4 gambar.');
            }
            foreach ($renderedImages as $image) {
                $mediaIds[] = $this->uploadMedia($this->mediaStorage->contents($image), $credentials, $publicationJob);
            }
        } else {
            $attachments = $menfess->attachments()->get();
            if ($attachments->isEmpty() || $attachments->count() > 4) {
                throw new PublicationNeedsReview('Menfess dengan gambar untuk X harus memiliki antara 1 dan 4 gambar.');
            }
            foreach ($attachments as $attachment) {
                $bytes = Storage::disk($attachment->disk)->get($attachment->path);
                if ($bytes === null || $bytes === '') {
                    throw new PublicationNeedsReview('Lampiran gambar menfess tidak dapat dibaca dari disk: '.$attachment->path);
                }
                $mediaIds[] = $this->uploadMedia($bytes, $credentials, $publicationJob);
            }
        }

        if (empty($mediaIds)) {
            throw new PublicationNeedsReview('Gagal mengunggah gambar untuk menfess. Publikasi tidak dapat dilanjutkan tanpa gambar.');
        }

        $postId = $this->createPost($menfess->body, $mediaIds, $credentials, $publicationJob);

        return new PublicationResult($postId);
    }

    /**
     * @return array{consumer_key: string, consumer_secret: string, access_token: string, access_token_secret: string}
     */
    private function credentials(): array
    {
        $consumerKey = trim((string) config('services.x.consumer_key'));
        $consumerSecret = trim((string) config('services.x.consumer_secret'));
        $accessToken = trim((string) config('services.x.access_token'));
        $accessTokenSecret = trim((string) config('services.x.access_token_secret'));

        if ($consumerKey === '' || $consumerSecret === '' || $accessToken === '' || $accessTokenSecret === '') {
            throw new PublicationNeedsReview('Konfigurasi OAuth 1.0a X belum lengkap. Isi X_API_KEY, X_API_KEY_SECRET, X_ACCESS_TOKEN, dan X_ACCESS_TOKEN_SECRET (User Context untuk @unwarfess), bukan Bearer Token Application-Only.');
        }

        if (str_starts_with($accessToken, 'AAAA')) {
            throw new PublicationNeedsReview('X_ACCESS_TOKEN tampak seperti Bearer Token Application-Only. Endpoint upload media X menolak autentikasi itu; gunakan Access Token OAuth 1.0a User Context beserta Access Token Secret.');
        }

        return [
            'consumer_key' => $consumerKey,
            'consumer_secret' => $consumerSecret,
            'access_token' => $accessToken,
            'access_token_secret' => $accessTokenSecret,
        ];
    }

    /** @param array{consumer_key: string, consumer_secret: string, access_token: string, access_token_secret: string} $credentials */
    private function uploadMedia(string $bytes, array $credentials, PublicationJob $publicationJob): string
    {
        // Per RFC 5849 (OAuth 1.0a) Section 3.4.1.3.1, multipart/form-data body parameters
        // are EXCLUDED from the signature base string.
        // The signature is calculated strictly from HTTP method, endpoint URL, and oauth_* parameters.
        $header = $this->oauth->authorizationHeader($credentials, 'POST', self::MEDIA_UPLOAD_URL);

        $response = $this->send(self::MEDIA_UPLOAD_URL, 'POST', 'upload media', $credentials, $publicationJob, function () use ($header, $bytes): Response {
            return $this->http()
                ->withHeaders(['Authorization' => $header])
                ->attach('media', $bytes, 'image.png', ['Content-Type' => 'image/png'])
                ->post(self::MEDIA_UPLOAD_URL);
        });

        $body = $response->json();
        $mediaId = is_array($body)
            ? ($body['data']['id'] ?? $body['media_id_string'] ?? $body['media_id'] ?? null)
            : null;

        return is_scalar($mediaId) && (string) $mediaId !== ''
            ? (string) $mediaId
            : throw new PublicationNeedsReview('X did not return a media ID.');
    }

    /**
     * @param  array<int, string>  $mediaIds
     * @param  array{consumer_key: string, consumer_secret: string, access_token: string, access_token_secret: string}  $credentials
     */
    private function createPost(string $text, array $mediaIds, array $credentials, PublicationJob $publicationJob): string
    {
        $header = $this->oauth->authorizationHeader($credentials, 'POST', self::CREATE_POST_URL);

        $response = $this->send(self::CREATE_POST_URL, 'POST', 'create post', $credentials, $publicationJob, function () use ($header, $text, $mediaIds): Response {
            $payload = ['text' => $text];
            if (! empty($mediaIds)) {
                $payload['media'] = ['media_ids' => array_values($mediaIds)];
            }

            return $this->http()
                ->withHeaders(['Authorization' => $header])
                ->asJson()
                ->post(self::CREATE_POST_URL, $payload);
        });

        $body = $response->json();
        $postId = is_array($body) ? ($body['data']['id'] ?? null) : null;

        return is_scalar($postId) && (string) $postId !== ''
            ? (string) $postId
            : throw new PublicationNeedsReview('X did not confirm post creation.');
    }

    /**
     * @param  array{consumer_key: string, consumer_secret: string, access_token: string, access_token_secret: string}  $credentials
     */
    private function send(string $url, string $method, string $stage, array $credentials, PublicationJob $publicationJob, Closure $request): Response
    {
        try {
            $response = $request();
        } catch (ConnectionException $exception) {
            Log::warning('X API request did not complete.', [
                'stage' => $stage,
                'endpoint' => $url,
                'method' => $method,
                'publication_job_id' => $publicationJob->id,
                'menfess_id' => $publicationJob->menfess_id,
                'api_host' => parse_url($url, PHP_URL_HOST) ?: null,
                'exception' => $exception::class,
                'message' => $this->sanitize($exception->getMessage()),
            ]);

            throw new PublicationNeedsReview('X API tidak merespons pada tahap '.$stage.' (koneksi/timeout). Hasil tidak diketahui; tinjau sebelum mencoba ulang.');
        }

        if (! $response->successful()) {
            $this->reportRejection($url, $method, $response, $credentials, $stage, $publicationJob);
        }

        return $response;
    }

    /**
     * @param  array{consumer_key: string, consumer_secret: string, access_token: string, access_token_secret: string}  $credentials
     */
    private function reportRejection(string $url, string $method, Response $response, array $credentials, string $stage, PublicationJob $publicationJob): never
    {
        $body = $response->json();
        $body = is_array($body) ? $body : [];
        $errors = is_array($body['errors'] ?? null) ? $body['errors'] : [];
        $firstError = is_array($errors[0] ?? null) ? $errors[0] : [];

        $errorCode = $firstError['code'] ?? null;
        $title = is_string($body['title'] ?? null)
            ? $body['title']
            : (is_string($firstError['title'] ?? null) ? $firstError['title'] : null);
        $rawMessage = is_string($firstError['message'] ?? null)
            ? $firstError['message']
            : (is_string($body['detail'] ?? null) ? $body['detail'] : 'X returned an unrecognized error response.');

        $message = $this->sanitize($this->redactSecrets($rawMessage, $credentials));

        Log::warning('X API rejected a publication request.', [
            'stage' => $stage,
            'endpoint' => $url,
            'method' => $method,
            'publication_job_id' => $publicationJob->id,
            'menfess_id' => $publicationJob->menfess_id,
            'api_host' => parse_url($url, PHP_URL_HOST) ?: null,
            'http_status' => $response->status(),
            'error_code' => is_int($errorCode) || is_string($errorCode) ? $errorCode : null,
            'title' => is_string($title) ? $this->sanitize($title) : null,
            'message' => $message,
            'authorization_scheme' => 'oauth_1a_user_context',
        ]);

        $codeLabel = $errorCode !== null ? ', code '.(string) $errorCode : '';

        $hint = '';
        if ($response->status() === 401) {
            $hint = ' (Periksa apakah Access Token/Secret di .env dibuat dari aplikasi X yang sama dan memiliki izin Read and Write)';
        }

        throw new PublicationNeedsReview('X menolak permintaan pada tahap '.$stage.' (HTTP '.$response->status().$codeLabel.'): '.$message.$hint);
    }

    private function http(): PendingRequest
    {
        return Http::connectTimeout(5)
            ->timeout(30)
            ->acceptJson();
    }

    /**
     * @param  array{consumer_key: string, consumer_secret: string, access_token: string, access_token_secret: string}  $credentials
     */
    private function redactSecrets(string $message, array $credentials): string
    {
        $secrets = array_values(array_filter($credentials, static fn (string $value): bool => $value !== ''));

        return str_replace($secrets, '[redacted]', $message);
    }

    private function sanitize(string $message): string
    {
        $message = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $message) ?? '';

        return mb_substr(trim($message), 0, 240);
    }
}
