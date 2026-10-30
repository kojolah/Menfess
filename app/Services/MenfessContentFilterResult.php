<?php

namespace App\Services;

final readonly class MenfessContentFilterResult
{
    /** @param list<string> $reviewReasons */
    public function __construct(
        public string $body,
        public int $censoredCount,
        public array $reviewReasons = [],
    ) {}

    public function needsReview(): bool
    {
        return $this->reviewReasons !== [];
    }
}
