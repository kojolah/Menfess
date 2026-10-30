<?php

namespace App\Services\Publishing;

use App\Models\PublicationJob;

class PublicationService
{
    public function publish(PublicationJob $publicationJob): PublicationResult
    {
        $limit = $publicationJob->platform === 'x' ? 280 : 2200;
        if (mb_strlen((string) $publicationJob->menfess->body) > $limit) {
            throw new PublicationNeedsReview('Sanitized text exceeds the character limit for the target platform.');
        }

        if (config('menfess.publisher_mode') !== 'live') {
            throw new PublicationNeedsReview('SIMULATION: no social media post was created.');
        }

        return match ($publicationJob->platform) {
            'instagram' => app(InstagramGraphPublisher::class)->publish($publicationJob),
            'x' => app(XApiPublisher::class)->publish($publicationJob),
            default => throw new PublicationNeedsReview('Unsupported social media platform.'),
        };
    }
}
