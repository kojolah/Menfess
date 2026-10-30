<?php

namespace Tests\Unit;

use App\Services\MenfessPublicDomainResolver;
use Tests\TestCase;

class MenfessPublicDomainResolverTest extends TestCase
{
    public function test_production_app_url_replaces_stale_localhost_template_domain(): void
    {
        config([
            'menfess.public_domain' => null,
            'app.url' => 'https://menfess.example.id/',
        ]);

        $domain = app(MenfessPublicDomainResolver::class)->resolve(['domain' => 'http://localhost:8000']);

        $this->assertSame('https://menfess.example.id', $domain);
    }

    public function test_explicit_public_domain_takes_precedence_over_template_and_app_url(): void
    {
        config([
            'menfess.public_domain' => 'https://www.unwarfess.example/',
            'app.url' => 'https://app.example.id',
        ]);

        $domain = app(MenfessPublicDomainResolver::class)->resolve(['domain' => 'https://template.example.id']);

        $this->assertSame('https://www.unwarfess.example', $domain);
    }

    public function test_public_template_domain_is_preserved_when_no_override_is_configured(): void
    {
        config([
            'menfess.public_domain' => null,
            'app.url' => 'http://localhost:8000',
        ]);

        $domain = app(MenfessPublicDomainResolver::class)->resolve(['domain' => 'https://menfess.example.id/path']);

        $this->assertSame('https://menfess.example.id', $domain);
    }
}
