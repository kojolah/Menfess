<?php

namespace App\Services\Publishing;

/**
 * Minimal OAuth 1.0a (HMAC-SHA1) signer for the X API user-context endpoints.
 *
 * Only the parts required by this application are implemented: building an
 * Authorization header and a signature base string per RFC 5849.
 */
class XOAuth1a
{
    /**
     * @param  array{consumer_key: string, consumer_secret: string, access_token: string, access_token_secret: string}  $credentials
     * @param  array<string, string>  $parameters  Query and form parameters to include in the signature (files excluded).
     */
    public function authorizationHeader(array $credentials, string $method, string $url, array $parameters = []): string
    {
        $oauth = [
            'oauth_consumer_key' => $credentials['consumer_key'],
            'oauth_nonce' => bin2hex(random_bytes(16)),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => (string) time(),
            'oauth_token' => $credentials['access_token'],
            'oauth_version' => '1.0',
        ];

        $oauth['oauth_signature'] = $this->signature(
            $credentials['consumer_secret'],
            $credentials['access_token_secret'],
            $method,
            $url,
            array_merge($parameters, $oauth),
        );

        $parts = [];
        foreach ($oauth as $key => $value) {
            $parts[] = $this->encode($key).'="'.$this->encode($value).'"';
        }

        return 'OAuth '.implode(', ', $parts);
    }

    /**
     * @param  array<string, string>  $parameters
     */
    public function signature(string $consumerSecret, string $tokenSecret, string $method, string $url, array $parameters): string
    {
        $signingKey = $this->encode($consumerSecret).'&'.$this->encode($tokenSecret);

        return base64_encode(hash_hmac('sha1', $this->signatureBaseString($method, $url, $parameters), $signingKey, true));
    }

    /**
     * @param  array<string, string>  $parameters
     */
    private function signatureBaseString(string $method, string $url, array $parameters): string
    {
        return strtoupper($method).'&'.$this->encode($this->baseUrl($url)).'&'.$this->encode($this->parameterString($parameters));
    }

    private function baseUrl(string $url): string
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');
        $port = $parts['port'] ?? null;

        $base = $scheme.'://'.$host.$path;

        if ($port !== null && ! (($scheme === 'https' && (int) $port === 443) || ($scheme === 'http' && (int) $port === 80))) {
            $base .= ':'.$port;
        }

        return $base;
    }

    /**
     * @param  array<string, string>  $parameters
     */
    private function parameterString(array $parameters): string
    {
        uksort($parameters, fn (string $left, string $right): int => strcmp($this->encode($left), $this->encode($right)));

        $pairs = [];
        foreach ($parameters as $key => $value) {
            $pairs[] = $this->encode((string) $key).'='.$this->encode((string) $value);
        }

        return implode('&', $pairs);
    }

    private function encode(string $value): string
    {
        return str_replace('%7E', '~', rawurlencode($value));
    }
}
