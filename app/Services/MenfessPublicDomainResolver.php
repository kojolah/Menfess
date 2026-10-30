<?php

namespace App\Services;

class MenfessPublicDomainResolver
{
    /** @param array<string, mixed>|null $theme */
    public function resolve(?array $theme = null): string
    {
        $candidates = [
            config('menfess.public_domain'),
            $theme['domain'] ?? null,
            config('app.url'),
        ];

        foreach ($candidates as $candidate) {
            $url = $this->normalize($candidate);
            if ($url !== null && ! $this->isLoopback($url)) {
                return $url;
            }
        }

        foreach ($candidates as $candidate) {
            $url = $this->normalize($candidate);
            if ($url !== null) {
                return $url;
            }
        }

        return '';
    }

    private function normalize(mixed $candidate): ?string
    {
        if (! is_string($candidate) || trim($candidate) === '') {
            return null;
        }

        $candidate = trim($candidate);
        if (! str_starts_with($candidate, 'https://') && ! str_starts_with($candidate, 'http://')) {
            $candidate = 'https://'.$candidate;
        }

        $parts = parse_url($candidate);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $scheme.'://'.$host.$port;
    }

    private function isLoopback(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'localhost'
            || $host === '::1'
            || filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false && str_starts_with($host, '127.')
            || str_ends_with($host, '.localhost');
    }
}
