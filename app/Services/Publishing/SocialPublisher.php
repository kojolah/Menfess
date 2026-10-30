<?php

namespace App\Services\Publishing;

use App\Models\PublicationJob;

interface SocialPublisher
{
    public function publish(PublicationJob $publicationJob): PublicationResult;
}
