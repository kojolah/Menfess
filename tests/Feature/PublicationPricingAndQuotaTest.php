<?php

namespace Tests\Feature;

use App\Jobs\ProcessPublication;
use App\Models\Category;
use App\Models\Menfess;
use App\Models\PublicationJob;
use App\Models\PublicationUsage;
use App\Models\User;
use App\Models\Wallet;
use App\Services\PublicationEligibilityService;
use App\Services\Publishing\PublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PublicationPricingAndQuotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_clock_uses_jakarta_time_and_resets_daily_quota_at_local_midnight(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);
        Carbon::setTestNow(Carbon::parse('2026-10-14 00:01:00', 'Asia/Jakarta'));
        $user = User::factory()->create();
        $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 4]);
        $service = app(PublicationEligibilityService::class);

        $yesterday = $this->createInstagramUsage($user, '2026-10-13 22:45:00');
        $quote = $service->quote($user->setRelation('wallet', $wallet), ['instagram']);

        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame(0, $quote['used_today']);
        $this->assertTrue($quote['free_instagram']);
        $this->assertSame(0, $quote['token_cost']);
        $this->assertSame(2, $yesterday->fresh()->tokens_charged);

        Carbon::setTestNow();
    }

    public function test_free_instagram_publication_requires_cooldown_and_caps_at_two_successes_per_wib_day(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);
        Carbon::setTestNow(Carbon::parse('2026-10-14 09:00:00', 'Asia/Jakarta'));
        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id, 'balance' => 10]);
        $service = app(PublicationEligibilityService::class);

        $first = $this->createMenfess($user, ['instagram']);
        $service->reserveForMenfess($first);
        $firstUsage = PublicationUsage::where('publication_job_id', $first->publicationJobs()->firstOrFail()->id)->firstOrFail();
        $this->assertTrue($firstUsage->is_free);
        $firstUsage->update(['published_at' => now()]);
        $first->publicationJobs()->update(['status' => 'published']);

        Carbon::setTestNow(Carbon::parse('2026-10-14 09:59:59', 'Asia/Jakarta'));
        $blockedQuote = $service->quote($user->fresh()->load('wallet'), ['instagram']);
        $this->assertFalse($blockedQuote['free_instagram']);
        $this->assertSame(2, $blockedQuote['token_cost']);
        $this->assertSame('10:00', $blockedQuote['next_free_at']?->format('H:i'));

        Carbon::setTestNow(Carbon::parse('2026-10-14 10:00:00', 'Asia/Jakarta'));
        $second = $this->createMenfess($user, ['instagram']);
        $service->reserveForMenfess($second);
        $secondJob = $second->publicationJobs()->firstOrFail();
        $secondUsage = PublicationUsage::where('publication_job_id', $secondJob->id)->firstOrFail();
        $this->assertTrue($secondUsage->is_free);
        $secondUsage->update(['published_at' => now()]);
        $secondJob->update(['status' => 'published']);

        Carbon::setTestNow(Carbon::parse('2026-10-14 11:00:00', 'Asia/Jakarta'));
        $thirdQuote = $service->quote($user->fresh()->load('wallet'), ['instagram']);
        $this->assertSame(2, $thirdQuote['used_today']);
        $this->assertFalse($thirdQuote['free_instagram']);
        $this->assertSame(2, $thirdQuote['token_cost']);

        Carbon::setTestNow(Carbon::parse('2026-10-15 00:01:00', 'Asia/Jakarta'));
        $tomorrowQuote = $service->quote($user->fresh()->load('wallet'), ['instagram']);
        $this->assertSame(0, $tomorrowQuote['used_today']);
        $this->assertTrue($tomorrowQuote['free_instagram']);

        Carbon::setTestNow();
    }

    public function test_global_free_quota_caps_at_10_successful_free_posts_across_all_accounts(): void
    {
        config([
            'app.timezone' => 'Asia/Jakarta',
            'menfess.global_free_posts_per_day' => 10,
            'menfess.instagram_free_posts_per_day' => 2,
        ]);
        Carbon::setTestNow(Carbon::parse('2026-10-14 08:00:00', 'Asia/Jakarta'));
        $service = app(PublicationEligibilityService::class);

        // Simulate 10 successful free posts from 5 different users (2 each)
        for ($i = 1; $i <= 5; $i++) {
            $user = User::factory()->create();
            Wallet::create(['user_id' => $user->id, 'balance' => 0]);

            $this->createInstagramUsage($user, '2026-10-14 08:10:00');
            $this->createInstagramUsage($user, '2026-10-14 09:30:00');
        }

        $this->assertSame(10, $service->successfulGlobalFreeCount(now()));
        $this->assertFalse($service->globalFreeAvailable(now()));

        // Now an 11th distinct user with 0 posts today attempts to get a free post
        $newUser = User::factory()->create();
        $newWallet = Wallet::create(['user_id' => $newUser->id, 'balance' => 4]);

        $quote = $service->quote($newUser->setRelation('wallet', $newWallet), ['instagram']);
        $this->assertFalse($quote['free_instagram'], 'Free Instagram must be blocked when global quota of 10 is reached.');
        $this->assertTrue($quote['global_exhausted']);
        $this->assertSame(10, $quote['global_used_today']);
        $this->assertSame(0, $quote['global_remaining_today']);
        $this->assertSame(2, $quote['token_cost'], 'Token cost must be charged when global free quota is exhausted.');

        // Rollover to next day in WIB (Asia/Jakarta)
        Carbon::setTestNow(Carbon::parse('2026-10-15 00:01:00', 'Asia/Jakarta'));
        $tomorrowQuote = $service->quote($newUser->fresh()->load('wallet'), ['instagram']);
        $this->assertTrue($tomorrowQuote['free_instagram']);
        $this->assertSame(0, $tomorrowQuote['global_used_today']);
        $this->assertSame(10, $tomorrowQuote['global_remaining_today']);
        $this->assertFalse($tomorrowQuote['global_exhausted']);

        Carbon::setTestNow();
    }

    public function test_non_published_menfess_drafts_rejected_or_paid_do_not_consume_global_free_quota(): void
    {
        config([
            'app.timezone' => 'Asia/Jakarta',
            'menfess.global_free_posts_per_day' => 10,
        ]);
        Carbon::setTestNow(Carbon::parse('2026-10-14 10:00:00', 'Asia/Jakarta'));
        $service = app(PublicationEligibilityService::class);

        $user = User::factory()->create();
        $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 10]);

        // 1. Draft menfess
        Menfess::create([
            'user_id' => $user->id,
            'category_id' => Category::firstOrCreate(['slug' => 'draft-test'], ['name' => 'Draft', 'badge_color' => '#FFF'])->id,
            'body' => 'Draft post',
            'status' => 'draft',
            'platforms' => ['instagram'],
        ]);

        // 2. Paid menfess (tokens charged)
        $paidMenfess = $this->createMenfess($user, ['instagram']);
        $paidJob = PublicationJob::create([
            'menfess_id' => $paidMenfess->id,
            'platform' => 'instagram',
            'status' => 'published',
            'tokens_reserved' => 2,
            'idempotency_key' => 'paid-job-'.$paidMenfess->id,
        ]);
        PublicationUsage::create([
            'user_id' => $user->id,
            'publication_job_id' => $paidJob->id,
            'platform' => 'instagram',
            'is_free' => false,
            'tokens_charged' => 2,
            'published_at' => now(),
        ]);

        // 3. Rejected menfess
        $rejectedMenfess = $this->createMenfess($user, ['instagram']);
        $rejectedMenfess->update(['status' => 'rejected', 'rejection_reason' => 'Spam content']);

        // Check global free count: must be 0!
        $this->assertSame(0, $service->successfulGlobalFreeCount(now()));
        $this->assertTrue($service->globalFreeAvailable(now()));

        Carbon::setTestNow();
    }

    public function test_free_quota_reservation_is_serialized_and_used_only_once_for_combined_submission(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-14 13:00:00', 'Asia/Jakarta'));
        $user = User::factory()->create();
        $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 5]);
        $service = app(PublicationEligibilityService::class);

        $combined = $this->createMenfess($user, ['instagram', 'x']);
        $firstIds = $service->reserveForMenfess($combined);
        $secondIds = $service->reserveForMenfess($combined);

        $this->assertSame($firstIds, $secondIds);
        $this->assertSame(1, PublicationUsage::where('user_id', $user->id)->where('is_free', true)->count());
        $this->assertSame(4, (int) $wallet->fresh()->balance);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertSame(0, (int) $combined->publicationJobs()->where('platform', 'instagram')->value('tokens_reserved'));
        $this->assertSame(1, (int) $combined->publicationJobs()->where('platform', 'x')->value('tokens_reserved'));

        $next = $this->createMenfess($user, ['instagram']);
        $service->reserveForMenfess($next);
        $this->assertFalse(PublicationUsage::where('publication_job_id', $next->publicationJobs()->firstOrFail()->id)->value('is_free'));
        $this->assertSame(2, (int) $wallet->fresh()->balance);

        Carbon::setTestNow();
    }

    public function test_platform_prices_are_central_and_insufficient_balance_is_rejected_without_debit(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);
        Carbon::setTestNow(Carbon::parse('2026-10-14 13:00:00', 'Asia/Jakarta'));
        $user = User::factory()->create();
        $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 1]);
        $service = app(PublicationEligibilityService::class);

        $this->assertSame(1, $service->quote($user->setRelation('wallet', $wallet), ['x'])['token_cost']);
        $this->assertSame(1, $service->quote($user->setRelation('wallet', $wallet), ['instagram', 'x'])['token_cost']);
        $this->createInstagramUsage($user, now()->subMinutes(10)->format('Y-m-d H:i:s'));
        $this->assertSame(2, $service->quote($user->fresh()->load('wallet'), ['instagram'])['token_cost']);
        $paidBoth = $service->quote($user->fresh()->load('wallet'), ['instagram', 'x']);
        $this->assertSame(3, $paidBoth['token_cost']);
        $this->assertSame(2, $paidBoth['shortfall']);

        $menfess = $this->createMenfess($user, ['instagram', 'x']);
        try {
            $service->reserveForMenfess($menfess);
            $this->fail('Expected insufficient token validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('platforms', $exception->errors());
        }

        $this->assertSame(1, (int) $wallet->fresh()->balance);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('publication_jobs', 1);

        Carbon::setTestNow();
    }

    public function test_simulation_releases_paid_tokens_because_no_platform_publication_was_attempted(): void
    {
        config(['menfess.publisher_mode' => 'simulation']);
        $user = User::factory()->create();
        $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 4]);
        $menfess = $this->createMenfess($user, ['x']);
        $service = app(PublicationEligibilityService::class);
        $publicationIds = $service->reserveForMenfess($menfess);

        app(ProcessPublication::class, ['publicationId' => $publicationIds[0]])
            ->handle(app(PublicationService::class));

        $this->assertSame(4, (int) $wallet->fresh()->balance);
        $this->assertSame('needs_review', $menfess->publicationJobs()->firstOrFail()->status);
        $this->assertDatabaseCount('wallet_transactions', 2);
    }

    private function createInstagramUsage(User $user, string $publishedAt): PublicationUsage
    {
        $menfess = $this->createMenfess($user, ['instagram']);
        $job = PublicationJob::create([
            'menfess_id' => $menfess->id,
            'platform' => 'instagram',
            'status' => 'published',
            'idempotency_key' => 'previous-'.$menfess->id.'-'.uniqid(),
        ]);

        return PublicationUsage::create([
            'user_id' => $user->id,
            'publication_job_id' => $job->id,
            'platform' => 'instagram',
            'is_free' => true,
            'tokens_charged' => 2,
            'published_at' => Carbon::parse($publishedAt, 'Asia/Jakarta'),
        ]);
    }

    /** @param array<int, string> $platforms */
    private function createMenfess(User $user, array $platforms): Menfess
    {
        $category = Category::firstOrCreate(['slug' => 'quota-test'], ['name' => 'Quota test', 'badge_color' => '#F6DDE8']);

        return Menfess::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'body' => 'Teks percobaan kuota.',
            'platforms' => $platforms,
            'status' => 'approved',
        ]);
    }
}
