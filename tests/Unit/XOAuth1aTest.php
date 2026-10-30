<?php

namespace Tests\Unit;

use App\Services\Publishing\XOAuth1a;
use PHPUnit\Framework\TestCase;

class XOAuth1aTest extends TestCase
{
    public function test_signature_matches_the_official_oauth_1a_example(): void
    {
        $parameters = [
            'status' => 'Hello Ladies + Gentlemen, a signed OAuth request!',
            'include_entities' => 'true',
            'oauth_consumer_key' => 'xvz1evFS4wEEPTGEFPHBog',
            'oauth_nonce' => 'kYjzVBB8Y0ZFabxSWbWovY3uYSQ2pTgmZeNu2VS4cg',
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => '1318622958',
            'oauth_token' => '370773112-GmHxMAgYyLbNEtIKZeRNFsMKPR9EyMZeS9weJAEb',
            'oauth_version' => '1.0',
        ];

        $signature = (new XOAuth1a)->signature(
            'kAcSOqF21Fu85e7zjz7ZN2U4ZRhfV3WpwPAoE3Z7kBw',
            'LswwdoUaIvS8ltyTt5jkRh4J50vUPVVHtR2YPi5kE',
            'POST',
            'https://api.twitter.com/1.1/statuses/update.json',
            $parameters,
        );

        $this->assertSame('hCtSmYh+iHYCEqBWrE7C7hYmtUk=', $signature);
    }

    public function test_authorization_header_uses_oauth_1a_and_not_a_bearer_scheme(): void
    {
        $header = (new XOAuth1a)->authorizationHeader(
            [
                'consumer_key' => 'consumer-key',
                'consumer_secret' => 'consumer-secret',
                'access_token' => 'access-token',
                'access_token_secret' => 'access-token-secret',
            ],
            'POST',
            'https://api.x.com/2/media/upload',
            ['media_category' => 'tweet_image'],
        );

        $this->assertStringStartsWith('OAuth ', $header);
        $this->assertStringContainsString('oauth_consumer_key="consumer-key"', $header);
        $this->assertStringContainsString('oauth_token="access-token"', $header);
        $this->assertStringContainsString('oauth_signature=', $header);
        $this->assertStringNotContainsString('Bearer ', $header);
    }
}
