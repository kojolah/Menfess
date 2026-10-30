<?php

namespace App\Services\Publishing;

final readonly class PublicationResult
{
    public function __construct(public string $externalPostId) {}
}
