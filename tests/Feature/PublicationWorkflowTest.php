<?php

namespace Tests\Feature;

use App\Jobs\ProcessPublication;
use App\Models\Category;
use App\Models\Menfess;
use App\Models\PublicationJob;
use App\Models\PublicationUsage;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Publishing\InstagramGraphPublisher;
use App\Services\Publishing\PublicationNeedsReview;
use App\Services\Publishing\PublicationResult;
use App\Services\Publishing\PublicationService;
use App\Services\Publishing\XApiPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_simulation_never_claims_a_social_post_was_published(): void
    {
        config(['menfess.publisher_mode' => 'simulation']);
        $menfess = $this->approvedMenfess(['instagram']);
        $job = $this->job($menfess, 'instagram');

        app(ProcessPublication::class, ['publicationId' => $job->id])->handle(app(PublicationService::class));

        $this->assertSame('needs_review', $job->fresh()->status);
        $this->assertSame('approved', $menfess->fresh()->status);
        $this->assertStringContainsString('SIMULATION', $job->fresh()->safe_error);
        $this->assertNull($job->fresh()->external_post_id);
    }

    public function test_platform_publication_jobs_keep_separate_statuses(): void
    {
        config(['menfess.publisher_mode' => 'simulation']);
        $menfess = $this->approvedMenfess(['instagram', 'x']);
        $instagram = $this->job($menfess, 'instagram');
        $x = $this->job($menfess, 'x');

        app(ProcessPublication::class, ['publicationId' => $instagram->id])->handle(app(PublicationService::class));

        $this->assertSame('needs_review', $instagram->fresh()->status);
        $this->assertSame('pending', $x->fresh()->status);
    }

    public function test_regular_user_cannot_open_publication_center(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->get(route('admin.publications.index'))
            ->assertForbidden();
    }

    public function test_confirmed_publication_is_not_sent_again_when_job_is_retried(): void
    {
        config(['menfess.publisher_mode' => 'live']);
        $menfess = $this->approvedMenfess(['x']);
        $job = $this->job($menfess, 'x');
        $menfess->generatedImages()->create(['platform' => 'x', 'width' => 1200, 'height' => 675, 'path' => 'placeholder.png', 'metadata' => []]);
        $publisher = \Mockery::mock(PublicationService::class);
        $publisher->shouldReceive('publish')->once()->withArgs(fn (PublicationJob $attempt): bool => $attempt->id === $job->id)->andReturn(new PublicationResult('post-123'));
        $worker = app(ProcessPublication::class, ['publicationId' => $job->id]);

        $worker->handle($publisher);
        $worker->handle($publisher);

        $this->assertSame('published', $job->fresh()->status);
        $this->assertSame('published', $menfess->fresh()->status);
        $this->assertSame('post-123', $job->fresh()->external_post_id);
    }

    public function test_x_api_receives_only_the_sanitized_persisted_text(): void
    {
        $this->configureXOauth();
        Storage::fake('public');
        Storage::disk('public')->put('rendered.png', 'png-bytes');
        Http::preventStrayRequests();
        Http::fake([
            'https://api.x.com/2/media/upload' => Http::response(['data' => ['id' => 'media-1']]),
            'https://api.x.com/2/tweets' => Http::response(['data' => ['id' => 'post-1']]),
        ]);
        $menfess = $this->approvedMenfess(['x']);
        $menfess->update(['body' => 'Pesan **** yang aman']);
        $job = $this->job($menfess, 'x');
        $this->addRenderedXImage($menfess);

        app(XApiPublisher::class)->publish($job->load('menfess'));

        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/2/tweets') && $request['text'] === 'Pesan **** yang aman');
    }

    public function test_x_api_can_publish_text_only_post_without_media(): void
    {
        $this->configureXOauth();
        Http::preventStrayRequests();
        Http::fake([
            'https://api.x.com/2/tweets' => Http::response(['data' => ['id' => 'post-text-1']]),
        ]);
        $menfess = $this->approvedMenfess(['x']);
        $job = $this->job($menfess, 'x');

        $result = app(XApiPublisher::class)->publish($job->load('menfess'));

        $this->assertSame('post-text-1', $result->externalPostId);
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/2/media/upload'));
        Http::assertSent(function ($request): bool {
            $authorization = $request->header('Authorization')[0] ?? '';

            return $request->url() === 'https://api.x.com/2/tweets'
                && str_starts_with($authorization, 'OAuth ')
                && ! str_contains($authorization, 'Bearer ')
                && $request['text'] === 'Teks anonim untuk publikasi.'
                && ! isset($request['media']);
        });
    }

    public function test_text_only_menfess_through_publication_job_skips_media_rendering_and_upload(): void
    {
        config(['menfess.publisher_mode' => 'live']);
        $this->configureXOauth();
        Http::preventStrayRequests();
        Http::fake([
            'https://api.x.com/2/tweets' => Http::response(['data' => ['id' => 'post-job-text-1']]),
        ]);
        $menfess = $this->approvedMenfess(['x']);
        $job = $this->job($menfess, 'x');

        app(ProcessPublication::class, ['publicationId' => $job->id])->handle(app(PublicationService::class));

        $this->assertSame('published', $job->fresh()->status);
        $this->assertSame('post-job-text-1', $job->fresh()->external_post_id);
        $this->assertSame(0, $menfess->fresh()->generatedImages()->where('platform', 'x')->count());
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/2/media/upload'));
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.x.com/2/tweets'
                && ! isset($request['media'])
                && $request['text'] === 'Teks anonim untuk publikasi.';
        });
    }

    public function test_x_media_upload_http_401_is_handled_as_failure_for_review(): void
    {
        config(['menfess.publisher_mode' => 'live']);
        $this->configureXOauth();
        Storage::fake('public');
        Storage::disk('public')->put('rendered.png', 'png-bytes');
        Http::preventStrayRequests();
        Http::fake([
            'https://api.x.com/2/media/upload' => Http::response([
                'title' => 'Unauthorized',
                'detail' => 'Unauthorized',
                'status' => 401,
            ], 401),
        ]);
        $menfess = $this->approvedMenfess(['x']);
        $job = $this->job($menfess, 'x');
        $this->addRenderedXImage($menfess);

        app(ProcessPublication::class, ['publicationId' => $job->id])->handle(app(PublicationService::class));

        $this->assertSame('needs_review', $job->fresh()->status);
        $this->assertStringContainsString('HTTP 401', $job->fresh()->safe_error);
        $this->assertNull($job->fresh()->external_post_id);
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/2/tweets'));
    }

    public function test_x_media_upload_failure_does_not_silently_publish_text_only(): void
    {
        config(['menfess.publisher_mode' => 'live']);
        $this->configureXOauth();
        Storage::fake('public');
        Storage::disk('public')->put('rendered.png', 'png-bytes');
        Http::preventStrayRequests();
        Http::fake([
            'https://api.x.com/2/media/upload' => Http::response(['error' => 'server_error'], 500),
            'https://api.x.com/2/tweets' => Http::response(['data' => ['id' => 'silent-post']]),
        ]);
        $menfess = $this->approvedMenfess(['x']);
        $job = $this->job($menfess, 'x');
        $this->addRenderedXImage($menfess);

        app(ProcessPublication::class, ['publicationId' => $job->id])->handle(app(PublicationService::class));

        $this->assertSame('needs_review', $job->fresh()->status);
        $this->assertSame('approved', $menfess->fresh()->status);
        $this->assertNull($job->fresh()->external_post_id);
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/2/tweets'));
    }

    public function test_x_api_signs_upload_and_post_requests_with_oauth_1a_user_context(): void
    {
        $this->configureXOauth();
        Storage::fake('public');
        Storage::disk('public')->put('rendered.png', 'png-bytes');
        Http::preventStrayRequests();
        Http::fake([
            'https://api.x.com/2/media/upload' => Http::response(['data' => ['id' => '1146866015550606592']]),
            'https://api.x.com/2/tweets' => Http::response(['data' => ['id' => 'post-1']]),
        ]);
        $menfess = $this->approvedMenfess(['x']);
        $job = $this->job($menfess, 'x');
        $this->addRenderedXImage($menfess);

        $result = app(XApiPublisher::class)->publish($job->load('menfess'));

        $this->assertSame('post-1', $result->externalPostId);
        Http::assertSent(function ($request): bool {
            $authorization = $request->header('Authorization')[0] ?? '';

            return $request->url() === 'https://api.x.com/2/media/upload'
                && str_starts_with($authorization, 'OAuth ')
                && str_contains($authorization, 'oauth_consumer_key="test-consumer-key"')
                && str_contains($authorization, 'oauth_token="test-access-token"')
                && $request->isMultipart()
                && collect($request->data())->contains(fn (array $part): bool => ($part['name'] ?? null) === 'media');
        });
        Http::assertSent(function ($request): bool {
            $authorization = $request->header('Authorization')[0] ?? '';

            return $request->url() === 'https://api.x.com/2/tweets'
                && str_starts_with($authorization, 'OAuth ')
                && ! str_contains($authorization, 'Bearer ')
                && $request['text'] === 'Teks anonim untuk publikasi.'
                && ($request['media']['media_ids'] ?? null) === ['1146866015550606592'];
        });
    }

    public function test_x_publication_requires_complete_oauth_1a_user_context_credentials(): void
    {
        config(['services.x.access_token' => null]);
        $menfess = $this->approvedMenfess(['x']);
        $job = $this->job($menfess, 'x');

        try {
            app(XApiPublisher::class)->publish($job->load('menfess'));
            $this->fail('Expected X publication to require OAuth 1.0a credentials.');
        } catch (PublicationNeedsReview $exception) {
            $this->assertStringContainsString('OAuth 1.0a', $exception->getMessage());
            $this->assertStringContainsString('X_ACCESS_TOKEN', $exception->getMessage());
        }
    }

    public function test_x_publication_rejects_application_only_bearer_access_tokens(): void
    {
        $this->configureXOauth(['access_token' => 'AAAA'.str_repeat('b', 40)]);
        $menfess = $this->approvedMenfess(['x']);
        $job = $this->job($menfess, 'x');

        try {
            app(XApiPublisher::class)->publish($job->load('menfess'));
            $this->fail('Expected X publication to reject an application-only bearer token.');
        } catch (PublicationNeedsReview $exception) {
            $this->assertStringContainsString('Bearer Token Application-Only', $exception->getMessage());
        }
    }

    public function test_x_api_error_is_reported_without_exposing_the_access_token(): void
    {
        $this->configureXOauth(['access_token' => 'x-secret-token']);
        Storage::fake('public');
        Storage::disk('public')->put('rendered.png', 'png-bytes');
        Http::preventStrayRequests();
        Http::fake([
            'https://api.x.com/2/media/upload' => Http::response([
                'title' => 'Forbidden',
                'detail' => 'You are not allowed to use this endpoint.',
                'type' => 'about:blank',
                'status' => 403,
            ], 403),
        ]);
        $menfess = $this->approvedMenfess(['x']);
        $job = $this->job($menfess, 'x');
        $this->addRenderedXImage($menfess);

        try {
            app(XApiPublisher::class)->publish($job->load('menfess'));
            $this->fail('Expected X API rejection to require review.');
        } catch (PublicationNeedsReview $exception) {
            $this->assertStringContainsString('HTTP 403', $exception->getMessage());
            $this->assertStringContainsString('You are not allowed to use this endpoint.', $exception->getMessage());
            $this->assertStringNotContainsString('x-secret-token', $exception->getMessage());
        }
    }

    public function test_x_media_upload_http_403_application_only_is_reported_for_review(): void
    {
        $this->configureXOauth();
        Storage::fake('public');
        Storage::disk('public')->put('rendered.png', 'png-bytes');
        Http::preventStrayRequests();
        Http::fake([
            'https://api.x.com/2/media/upload' => Http::response([
                'title' => 'Unsupported Authentication',
                'detail' => 'Authenticating with OAuth 2.0 Application-Only is forbidden for this endpoint. Supported authentication types are [OAuth 1.0a User Context, OAuth 2.0 User Context].',
                'status' => 403,
            ], 403),
        ]);
        $menfess = $this->approvedMenfess(['x']);
        $job = $this->job($menfess, 'x');
        $this->addRenderedXImage($menfess);

        try {
            app(XApiPublisher::class)->publish($job->load('menfess'));
            $this->fail('Expected application-only 403 to require review.');
        } catch (PublicationNeedsReview $exception) {
            $this->assertStringContainsString('HTTP 403', $exception->getMessage());
            $this->assertStringContainsString('OAuth 2.0 Application-Only', $exception->getMessage());
        }

        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.x.com/2/media/upload'
            && str_starts_with($request->header('Authorization')[0] ?? '', 'OAuth '));
    }

    public function test_x_timeout_during_create_post_is_treated_as_unknown_outcome(): void
    {
        config(['menfess.publisher_mode' => 'live']);
        $this->configureXOauth();
        Storage::fake('public');
        Storage::disk('public')->put('rendered.png', 'png-bytes');
        Http::preventStrayRequests();
        Http::fake([
            'https://api.x.com/2/media/upload' => Http::response(['data' => ['id' => 'media-1']]),
            'https://api.x.com/2/tweets' => Http::failedConnection(),
        ]);
        $menfess = $this->approvedMenfess(['x']);
        $job = $this->job($menfess, 'x');
        $this->addRenderedXImage($menfess);

        app(ProcessPublication::class, ['publicationId' => $job->id])->handle(app(PublicationService::class));

        $this->assertSame('needs_review', $job->fresh()->status);
        $this->assertSame('approved', $menfess->fresh()->status);
        $this->assertNull($job->fresh()->external_post_id);
        $this->assertStringContainsString('timeout', $job->fresh()->safe_error);
        $this->assertStringContainsString('tidak diketahui', $job->fresh()->safe_error);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.x.com/2/tweets');
    }

    public function test_x_publication_stores_external_post_id_when_x_confirms_creation(): void
    {
        config(['menfess.publisher_mode' => 'live']);
        $this->configureXOauth();
        Storage::fake('public');
        Storage::disk('public')->put('rendered.png', 'png-bytes');
        Http::preventStrayRequests();
        Http::fake([
            'https://api.x.com/2/media/upload' => Http::response(['data' => ['id' => 'media-1']]),
            'https://api.x.com/2/tweets' => Http::response(['data' => ['id' => '1987654321']]),
        ]);
        $menfess = $this->approvedMenfess(['x']);
        $job = $this->job($menfess, 'x');
        $this->addRenderedXImage($menfess);

        app(ProcessPublication::class, ['publicationId' => $job->id])->handle(app(PublicationService::class));

        $this->assertSame('published', $job->fresh()->status);
        $this->assertSame('published', $menfess->fresh()->status);
        $this->assertSame('1987654321', $job->fresh()->external_post_id);
    }

    public function test_live_x_publication_failure_does_not_refund_or_debit_tokens_again(): void
    {
        config(['menfess.publisher_mode' => 'live']);
        $this->configureXOauth();
        Storage::fake('public');
        Storage::disk('public')->put('rendered.png', 'png-bytes');
        Http::preventStrayRequests();
        Http::fake([
            'https://api.x.com/2/media/upload' => Http::response([
                'title' => 'Forbidden',
                'detail' => 'You are not allowed to use this endpoint.',
                'status' => 403,
            ], 403),
        ]);
        $menfess = $this->approvedMenfess(['x']);
        $wallet = Wallet::create(['user_id' => $menfess->user_id, 'balance' => 4]);
        $wallet->transactions()->create([
            'type' => 'publication',
            'amount' => -1,
            'balance_before' => 5,
            'balance_after' => 4,
            'idempotency_key' => 'menfess:'.$menfess->id.':publication-debit',
            'reason' => 'Reservasi publikasi: x',
        ]);
        $job = $this->job($menfess, 'x');
        $job->update(['tokens_reserved' => 1]);
        $this->addRenderedXImage($menfess);

        app(ProcessPublication::class, ['publicationId' => $job->id])->handle(app(PublicationService::class));

        $this->assertSame('needs_review', $job->fresh()->status);
        $this->assertSame(4, (int) $wallet->fresh()->balance);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_x_publication_job_records_the_sanitized_x_error_for_admin_review(): void
    {
        config(['menfess.publisher_mode' => 'live']);
        $this->configureXOauth(['access_token' => 'x-secret-token']);
        Storage::fake('public');
        Storage::disk('public')->put('rendered.png', 'png-bytes');
        Http::preventStrayRequests();
        Http::fake([
            'https://api.x.com/2/media/upload' => Http::response([
                'title' => 'Forbidden',
                'detail' => 'You are not allowed to use this endpoint.',
                'type' => 'about:blank',
                'status' => 403,
            ], 403),
        ]);
        $menfess = $this->approvedMenfess(['x']);
        $job = $this->job($menfess, 'x');
        $this->addRenderedXImage($menfess);

        app(ProcessPublication::class, ['publicationId' => $job->id])->handle(app(PublicationService::class));

        $this->assertSame('needs_review', $job->fresh()->status);
        $this->assertStringContainsString('HTTP 403', $job->fresh()->safe_error);
        $this->assertStringNotContainsString('x-secret-token', $job->fresh()->safe_error);
    }

    public function test_instagram_publisher_uses_meta_environment_credentials(): void
    {
        config(['menfess.publisher_mode' => 'live']);
        $this->configureInstagramLogin();
        Http::preventStrayRequests();
        Http::fakeSequence()
            ->push(['id' => 'app-scoped-id', 'user_id' => '17841400000000001', 'username' => 'campus_test'])
            ->push(['id' => 'container-1'])
            ->push(['id' => 'container-1', 'status_code' => 'FINISHED', 'status' => 'Finished: Media is ready to publish.'])
            ->push(['id' => 'published-1']);
        $menfess = $this->approvedMenfess(['instagram']);
        $job = $this->job($menfess, 'instagram');
        $this->addCloudinaryInstagramImage($menfess);

        app(ProcessPublication::class, ['publicationId' => $job->id])->handle(app(PublicationService::class));

        $this->assertSame('published', $job->fresh()->status);
        $this->assertSame('published-1', $job->fresh()->external_post_id);
        Http::assertSent(fn ($request): bool => $request->method() === 'GET'
            && str_contains($request->url(), 'https://graph.instagram.com/v25.0/me')
            && $request['fields'] === 'user_id,username'
            && $request->hasHeader('Authorization', 'Bearer test-meta-token'));
        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/17841400000000001/media')
            && $request->hasHeader('Authorization', 'Bearer test-meta-token')
            && str_contains($request['image_url'], '/image/upload/f_jpg/'));
        Http::assertSent(fn ($request): bool => $request->method() === 'GET'
            && parse_url($request->url(), PHP_URL_PATH) === '/v25.0/container-1'
            && $request['fields'] === 'status_code,status');
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/media_publish'));
    }

    public function test_instagram_container_is_polled_until_ready_before_publishing(): void
    {
        $this->configureInstagramLogin();
        Http::preventStrayRequests();
        Http::fakeSequence()
            ->push(['id' => 'app-scoped-id', 'user_id' => '17841400000000001', 'username' => 'campus_test'])
            ->push(['id' => 'container-1'])
            ->push(['id' => 'container-1', 'status_code' => 'IN_PROGRESS', 'status' => 'Media is processing.'])
            ->push(['id' => 'container-1', 'status_code' => 'FINISHED', 'status' => 'Media is ready.'])
            ->push(['id' => 'published-1']);
        $menfess = $this->approvedMenfess(['instagram']);
        $job = $this->job($menfess, 'instagram');
        $this->addCloudinaryInstagramImage($menfess);

        $result = app(InstagramGraphPublisher::class)->publish($job->load('menfess'));

        $this->assertSame('published-1', $result->externalPostId);
        Http::assertSentCount(5);
        $paths = Http::recorded()->map(fn ($pair) => parse_url($pair[0]->url(), PHP_URL_PATH))->all();
        $this->assertSame(1, count(array_filter($paths, fn (?string $path): bool => $path === '/v25.0/17841400000000001/media')));
        $this->assertSame(2, count(array_filter($paths, fn (?string $path): bool => $path === '/v25.0/container-1')));
        $this->assertSame(1, count(array_filter($paths, fn (?string $path): bool => $path === '/v25.0/17841400000000001/media_publish')));
    }

    public function test_instagram_account_mismatch_is_stopped_before_media_creation(): void
    {
        $this->configureInstagramLogin();
        Http::preventStrayRequests();
        Http::fakeSequence()->push(['id' => 'app-scoped-id', 'user_id' => '17841400000000002', 'username' => 'another_account']);
        $menfess = $this->approvedMenfess(['instagram']);
        $job = $this->job($menfess, 'instagram');
        $this->addCloudinaryInstagramImage($menfess);

        try {
            app(InstagramGraphPublisher::class)->publish($job->load('menfess'));
            $this->fail('Expected an account mismatch to require review.');
        } catch (PublicationNeedsReview $exception) {
            $this->assertStringContainsString('user_id does not match META_INSTAGRAM_ACCOUNT_ID', $exception->getMessage());
        }

        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request->method() === 'GET'
            && str_contains($request->url(), 'https://graph.instagram.com/v25.0/me'));
    }

    public function test_instagram_api_error_is_reported_without_exposing_the_access_token(): void
    {
        $this->configureInstagramLogin();
        Http::preventStrayRequests();
        Http::fakeSequence()
            ->push(['id' => 'app-scoped-id', 'user_id' => '17841400000000001', 'username' => 'campus_test'])
            ->push([
                'error' => [
                    'message' => 'Invalid OAuth access token test-meta-token',
                    'type' => 'OAuthException',
                    'code' => 190,
                    'error_subcode' => 463,
                    'fbtrace_id' => 'trace-for-test',
                ],
            ], 400);
        $menfess = $this->approvedMenfess(['instagram']);
        $job = $this->job($menfess, 'instagram');
        $this->addCloudinaryInstagramImage($menfess);

        try {
            app(InstagramGraphPublisher::class)->publish($job->load('menfess'));
            $this->fail('Expected Instagram API rejection to require review.');
        } catch (PublicationNeedsReview $exception) {
            $this->assertStringContainsString('HTTP 400, code 190, subcode 463', $exception->getMessage());
            $this->assertStringContainsString('trace-for-test', $exception->getMessage());
            $this->assertStringContainsString('host graph.instagram.com', $exception->getMessage());
            $this->assertStringNotContainsString('test-meta-token', $exception->getMessage());
        }
    }

    public function test_publication_job_keeps_the_sanitized_meta_error_for_admin_review(): void
    {
        config(['menfess.publisher_mode' => 'live']);
        $this->configureInstagramLogin();
        Http::preventStrayRequests();
        Http::fakeSequence()
            ->push(['id' => 'app-scoped-id', 'user_id' => '17841400000000001', 'username' => 'campus_test'])
            ->push([
                'error' => [
                    'message' => 'Invalid OAuth access token test-meta-token',
                    'type' => 'OAuthException',
                    'code' => 190,
                    'error_subcode' => 463,
                    'fbtrace_id' => 'trace-for-job-test',
                ],
            ], 400);
        $menfess = $this->approvedMenfess(['instagram']);
        $job = $this->job($menfess, 'instagram');
        $this->addCloudinaryInstagramImage($menfess);

        app(ProcessPublication::class, ['publicationId' => $job->id])->handle(app(PublicationService::class));

        $this->assertSame('needs_review', $job->fresh()->status);
        $this->assertStringContainsString('HTTP 400, code 190, subcode 463', $job->fresh()->safe_error);
        $this->assertStringContainsString('trace-for-job-test', $job->fresh()->safe_error);
        $this->assertStringNotContainsString('test-meta-token', $job->fresh()->safe_error);
    }

    /** @param array<int, string> $platforms */
    private function approvedMenfess(array $platforms): Menfess
    {
        $category = Category::create(['name' => 'umum!', 'slug' => 'umum', 'badge_color' => '#F7ECCD']);

        return Menfess::create([
            'user_id' => User::factory()->create()->id,
            'category_id' => $category->id,
            'body' => 'Teks anonim untuk publikasi.',
            'platforms' => $platforms,
            'status' => 'approved',
        ]);
    }

    private function job(Menfess $menfess, string $platform): PublicationJob
    {
        $job = PublicationJob::create([
            'menfess_id' => $menfess->id,
            'platform' => $platform,
            'status' => 'pending',
            'idempotency_key' => 'test-'.$menfess->id.'-'.$platform,
        ]);
        if ($platform === 'instagram') {
            PublicationUsage::create(['user_id' => $menfess->user_id, 'publication_job_id' => $job->id, 'platform' => 'instagram', 'is_free' => true]);
        }

        return $job;
    }

    /**
     * @param  array{consumer_key?: string, consumer_secret?: string, access_token?: string, access_token_secret?: string}  $overrides
     */
    private function configureXOauth(array $overrides = []): void
    {
        config([
            'services.x.consumer_key' => $overrides['consumer_key'] ?? 'test-consumer-key',
            'services.x.consumer_secret' => $overrides['consumer_secret'] ?? 'test-consumer-secret',
            'services.x.access_token' => $overrides['access_token'] ?? 'test-access-token',
            'services.x.access_token_secret' => $overrides['access_token_secret'] ?? 'test-access-token-secret',
        ]);
    }

    private function addRenderedXImage(Menfess $menfess): void
    {
        $menfess->generatedImages()->create([
            'platform' => 'x',
            'width' => 1200,
            'height' => 675,
            'disk' => 'public',
            'path' => 'rendered.png',
            'metadata' => [],
        ]);
    }

    private function configureInstagramLogin(): void
    {
        config([
            'services.meta.graph_version' => 'v25.0',
            'services.meta.instagram_account_id' => '17841400000000001',
            'services.meta.instagram_access_token' => 'test-meta-token',
            'services.meta.instagram_token_expires_at' => null,
            'services.cloudinary.cloud_name' => 'test-cloud',
            'services.cloudinary.api_key' => 'test-api-key',
            'services.cloudinary.api_secret' => 'test-api-secret',
        ]);
    }

    private function addCloudinaryInstagramImage(Menfess $menfess): void
    {
        $menfess->generatedImages()->create([
            'platform' => 'instagram',
            'width' => 1080,
            'height' => 1350,
            'disk' => 'cloudinary',
            'path' => 'campus-menfess/test/rendered.png',
            'metadata' => ['secure_url' => 'https://res.cloudinary.com/test-cloud/image/upload/v1/campus-menfess/test/rendered.png'],
        ]);
    }
}
