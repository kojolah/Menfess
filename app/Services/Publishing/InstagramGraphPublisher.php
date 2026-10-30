<?php

namespace App\Services\Publishing;

use App\Models\GeneratedImage;
use App\Models\PublicationJob;
use App\Services\CloudinaryMediaStorage;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class InstagramGraphPublisher implements SocialPublisher
{
    private const CONTAINER_STATUS_MAX_ATTEMPTS = 15;

    private const CONTAINER_STATUS_POLL_DELAY_MICROSECONDS = 1_000_000;

    public function __construct(private readonly CloudinaryMediaStorage $mediaStorage) {}

    public function publish(PublicationJob $publicationJob): PublicationResult
    {
        $account = $this->verifyAccount();
        $images = $publicationJob->menfess->generatedImages()->where('platform', 'instagram')->oldest()->get();
        if ($images->isEmpty() || $images->count() > 10) {
            throw new PublicationNeedsReview('Instagram publication requires between one and ten rendered slides.');
        }

        $baseUrl = $this->apiBase().'/'.$account['account_id'];
        $imageUrls = $images->map(fn (GeneratedImage $image): string => $this->instagramImageUrl($image))->all();

        if (count($imageUrls) === 1) {
            $container = $this->post($baseUrl.'/media', [
                'image_url' => $imageUrls[0],
                'caption' => $publicationJob->menfess->body,
            ], $account['access_token'], 'create media container');
        } else {
            $children = [];
            foreach ($imageUrls as $url) {
                $child = $this->post($baseUrl.'/media', ['image_url' => $url, 'is_carousel_item' => 'true'], $account['access_token'], 'create carousel item');
                $childId = $child['id'] ?? throw new PublicationNeedsReview('Instagram did not return a media container ID.');
                $this->waitUntilContainerReady((string) $childId, $account['access_token']);
                $children[] = (string) $childId;
            }
            $container = $this->post($baseUrl.'/media', [
                'media_type' => 'CAROUSEL',
                'children' => implode(',', $children),
                'caption' => $publicationJob->menfess->body,
            ], $account['access_token'], 'create carousel container');
        }

        $containerId = $container['id'] ?? throw new PublicationNeedsReview('Instagram did not return a publication container ID.');
        $this->waitUntilContainerReady((string) $containerId, $account['access_token']);
        $published = $this->post($baseUrl.'/media_publish', ['creation_id' => $containerId], $account['access_token'], 'publish media container');

        return new PublicationResult((string) ($published['id'] ?? throw new PublicationNeedsReview('Instagram did not confirm publication.')));
    }

    private function waitUntilContainerReady(string $containerId, string $token): void
    {
        $url = $this->apiBase().'/'.$containerId;

        for ($attempt = 1; $attempt <= self::CONTAINER_STATUS_MAX_ATTEMPTS; $attempt++) {
            $container = $this->request('GET', $url, ['fields' => 'status_code,status'], $token, 'check media container status');
            $statusCode = strtoupper((string) ($container['status_code'] ?? ''));

            if ($statusCode === 'FINISHED') {
                return;
            }

            if (in_array($statusCode, ['ERROR', 'EXPIRED', 'PUBLISHED'], true)) {
                $detail = is_string($container['status'] ?? null)
                    ? mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $container['status']) ?? ''), 0, 200)
                    : 'No status detail was provided.';

                throw new PublicationNeedsReview('Instagram media container cannot be published (status '.$statusCode.'): '.$detail);
            }

            if (! in_array($statusCode, ['IN_PROGRESS', ''], true)) {
                throw new PublicationNeedsReview('Instagram returned an unrecognized media container status.');
            }

            if ($attempt < self::CONTAINER_STATUS_MAX_ATTEMPTS) {
                usleep(self::CONTAINER_STATUS_POLL_DELAY_MICROSECONDS);
            }
        }

        throw new PublicationNeedsReview('Instagram media container is still processing after '.self::CONTAINER_STATUS_MAX_ATTEMPTS.' checks; publication was not attempted.');
    }

    /** Validate the Instagram Login token against the configured professional account. */
    private function verifyAccount(): array
    {
        $account = $this->account();
        $profile = $this->request(
            'GET',
            $this->apiBase().'/me',
            ['fields' => 'user_id,username'],
            $account['access_token'],
            'validate Instagram account and token',
        );

        if ((string) ($profile['user_id'] ?? '') !== $account['account_id']) {
            throw new PublicationNeedsReview('Instagram token account user_id does not match META_INSTAGRAM_ACCOUNT_ID. Use the professional account user_id returned by Instagram Login.');
        }

        return $account;
    }

    /** @return array<string, mixed> */
    private function post(string $url, array $payload, string $token, string $stage): array
    {
        return $this->request('POST', $url, $payload, $token, $stage);
    }

    /** @param array<string, string> $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $url, array $payload, string $token, string $stage): array
    {
        try {
            $request = Http::withToken($token)->acceptJson()->timeout(30);
            $response = $method === 'GET'
                ? $request->get($url, $payload)
                : $request->post($url, $payload);
        } catch (RequestException $exception) {
            // Preserve Meta's response when the HTTP handler throws on an error status.
            $response = $exception->response;
        }

        if (! $response->successful()) {
            $apiHost = parse_url($url, PHP_URL_HOST);
            $error = $response->json('error');
            $error = is_array($error) ? $error : [];
            $message = is_string($error['message'] ?? null) ? $error['message'] : 'Meta returned an unrecognized error response.';
            $message = str_replace($token, '[redacted]', $message);
            $message = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $message) ?? 'Meta returned an error.';
            $message = mb_substr(trim($message), 0, 240);
            $errorCode = $error['code'] ?? null;
            $errorSubcode = $error['error_subcode'] ?? null;
            $traceId = is_string($error['fbtrace_id'] ?? null) ? mb_substr($error['fbtrace_id'], 0, 100) : null;

            Log::warning('Instagram Graph API rejected a publication request.', [
                'stage' => $stage,
                'api_host' => is_string($apiHost) ? $apiHost : null,
                'http_status' => $response->status(),
                'error_type' => is_string($error['type'] ?? null) ? mb_substr($error['type'], 0, 100) : null,
                'error_code' => is_int($errorCode) || is_string($errorCode) ? $errorCode : null,
                'error_subcode' => is_int($errorSubcode) || is_string($errorSubcode) ? $errorSubcode : null,
                'is_transient' => is_bool($error['is_transient'] ?? null) ? $error['is_transient'] : null,
                'fbtrace_id' => $traceId,
                'message' => $message,
            ]);

            $codeLabel = $errorCode !== null ? ', code '.(string) $errorCode : '';
            $subcodeLabel = $errorSubcode !== null ? ', subcode '.(string) $errorSubcode : '';
            $traceLabel = $traceId !== null ? ', trace '.$traceId : '';

            $hostLabel = is_string($apiHost) ? ', host '.$apiHost : '';

            throw new PublicationNeedsReview('Meta Instagram menolak permintaan pada tahap '.$stage.' (HTTP '.$response->status().$codeLabel.$subcodeLabel.$traceLabel.$hostLabel.'): '.$message);
        }

        return $response->json() ?? [];
    }

    private function instagramImageUrl(GeneratedImage $image): string
    {
        $url = $this->mediaStorage->url($image);
        $parsed = parse_url($url);

        if (($parsed['scheme'] ?? null) !== 'https'
            || ($parsed['host'] ?? null) !== 'res.cloudinary.com'
            || ! str_contains($parsed['path'] ?? '', '/image/upload/')) {
            throw new PublicationNeedsReview('Instagram publishing requires the rendered image to be available from Cloudinary over HTTPS.');
        }

        // Instagram content publishing accepts JPEG images; Cloudinary serves a JPEG
        // derivative while retaining the original PNG asset and URL in our database.
        return str_replace('/image/upload/', '/image/upload/f_jpg/', $url);
    }

    /** @return array{account_id: string, access_token: string, token_expires_at: ?CarbonImmutable} */
    private function account(): array
    {
        $accountId = trim((string) config('services.meta.instagram_account_id'));
        $token = trim((string) config('services.meta.instagram_access_token'));

        if ($accountId === '' || $token === '') {
            throw new PublicationNeedsReview('META_INSTAGRAM_ACCOUNT_ID and META_INSTAGRAM_ACCESS_TOKEN must both be configured for Instagram Login.');
        }
        if (! preg_match('/^\d+$/', $accountId)) {
            throw new PublicationNeedsReview('META_INSTAGRAM_ACCOUNT_ID must be the numeric Instagram professional account ID.');
        }

        $expiresAt = config('services.meta.instagram_token_expires_at');
        $expiresAt = $expiresAt ? CarbonImmutable::parse($expiresAt) : null;
        if ($expiresAt?->isPast()) {
            throw new PublicationNeedsReview('Instagram access token has expired.');
        }

        return [
            'account_id' => $accountId,
            'access_token' => $token,
            'token_expires_at' => $expiresAt,
        ];
    }

    private function apiBase(): string
    {
        $version = (string) config('services.meta.graph_version', 'v25.0');
        if (! preg_match('/^v\d+\.\d+$/', $version)) {
            throw new RuntimeException('META_GRAPH_VERSION must use the format vNN.N.');
        }

        return 'https://graph.instagram.com/'.$version;
    }
}
