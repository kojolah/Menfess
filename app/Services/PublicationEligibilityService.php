<?php

namespace App\Services;

use App\Models\DailyGlobalQuota;
use App\Models\Menfess;
use App\Models\PublicationUsage;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublicationEligibilityService
{
    /**
     * Quote the current free Instagram eligibility, quotas, and token cost.
     * A free allowance covers Instagram only; X remains chargeable when selected.
     *
     * @param  array<int, string>  $platforms
     * @return array{
     *     free_instagram: bool,
     *     used_today: int,
     *     remaining_today: int,
     *     daily_limit: int,
     *     global_used_today: int,
     *     global_remaining_today: int,
     *     global_limit: int,
     *     global_exhausted: bool,
     *     reset_time: string,
     *     next_free_at: ?Carbon,
     *     token_cost: int,
     *     balance: int,
     *     shortfall: int
     * }
     */
    public function quote(User $user, array $platforms, ?Carbon $at = null): array
    {
        $now = ($at ?? now())->copy()->timezone(config('app.timezone', 'Asia/Jakarta'));
        $freeInstagram = in_array('instagram', $platforms, true) && $this->instagramFreeAvailable($user->id, $now);
        $usedToday = $this->successfulFreeInstagramCount($user->id, $now);
        $userLimit = max(0, (int) config('menfess.instagram_free_posts_per_day', 2));

        $globalLimit = max(0, (int) config('menfess.global_free_posts_per_day', 10));
        $globalSuccessful = $this->successfulGlobalFreeCount($now);
        $globalReservations = $this->activeGlobalFreeReservations();
        $globalUsedToday = min($globalLimit, $globalSuccessful + $globalReservations);
        $globalRemainingToday = max(0, $globalLimit - $globalUsedToday);
        $globalExhausted = $globalRemainingToday <= 0;

        $costs = config('menfess.publication_token_costs', ['instagram' => 2, 'x' => 1]);
        $tokenCost = 0;

        foreach (array_unique($platforms) as $platform) {
            if (! in_array($platform, ['instagram', 'x'], true)) {
                continue;
            }
            if ($platform === 'instagram' && $freeInstagram) {
                continue;
            }
            $tokenCost += max(0, (int) ($costs[$platform] ?? 0));
        }

        $balance = (int) ($user->wallet?->balance ?? 0);
        $latestFree = PublicationUsage::query()
            ->where('user_id', $user->id)
            ->where('platform', 'instagram')
            ->where('is_free', true)
            ->whereNotNull('published_at')
            ->latest('published_at')
            ->value('published_at');
        $cooldown = max(0, (int) config('menfess.instagram_free_cooldown_minutes', 60));
        $nextFreeAt = $latestFree ? Carbon::parse($latestFree)->addMinutes($cooldown) : null;
        if ($usedToday >= $userLimit) {
            $nextFreeAt = null;
        }

        return [
            'free_instagram' => $freeInstagram,
            'used_today' => $usedToday,
            'remaining_today' => max(0, $userLimit - $usedToday),
            'daily_limit' => $userLimit,
            'global_used_today' => $globalUsedToday,
            'global_remaining_today' => $globalRemainingToday,
            'global_limit' => $globalLimit,
            'global_exhausted' => $globalExhausted,
            'reset_time' => '00:00 WIB',
            'next_free_at' => $nextFreeAt?->greaterThan($now) ? $nextFreeAt : null,
            'token_cost' => $tokenCost,
            'balance' => $balance,
            'shortfall' => max(0, $tokenCost - $balance),
        ];
    }

    /**
     * Create platform jobs and reserve the one free Instagram allowance or debit tokens once.
     * Uses atomic transactions and table locks to prevent concurrency race conditions on quotas.
     *
     * @return array<int, int>
     */
    public function reserveForMenfess(Menfess $menfess): array
    {
        return DB::transaction(function () use ($menfess): array {
            $existingJobs = $menfess->publicationJobs()->pluck('id')->all();
            if ($existingJobs !== []) {
                return $existingJobs;
            }

            $user = User::query()->whereKey($menfess->user_id)->lockForUpdate()->firstOrFail();
            $wallet = Wallet::query()->firstOrCreate(['user_id' => $user->id]);
            $wallet->refresh();

            // Atomic lock for daily global quota to serialize concurrent free slot claims
            $todayDate = now(config('app.timezone', 'Asia/Jakarta'))->format('Y-m-d');
            DailyGlobalQuota::query()->firstOrCreate(['date' => $todayDate], ['published_free_count' => 0]);
            DailyGlobalQuota::query()->where('date', $todayDate)->lockForUpdate()->first();

            $quote = $this->quote($user->setRelation('wallet', $wallet), $menfess->platforms ?? []);

            if ($quote['shortfall'] > 0) {
                throw ValidationException::withMessages([
                    'platforms' => 'Saldo token kurang '.number_format($quote['shortfall']).' token. Silakan top up token via QRIS untuk melanjutkan.',
                ]);
            }

            $costs = config('menfess.publication_token_costs', ['instagram' => 2, 'x' => 1]);
            $publicationIds = [];
            $totalCharge = 0;

            foreach (array_values(array_unique($menfess->platforms ?? [])) as $platform) {
                $cost = $platform === 'instagram' && $quote['free_instagram']
                    ? 0
                    : max(0, (int) ($costs[$platform] ?? 0));

                $job = $menfess->publicationJobs()->firstOrCreate(
                    ['platform' => $platform],
                    [
                        'status' => 'pending',
                        'tokens_reserved' => $cost,
                        'idempotency_key' => 'menfess-'.$menfess->id.'-'.$platform,
                    ],
                );
                $job->update(['tokens_reserved' => $cost]);
                $totalCharge += $cost;
                $publicationIds[] = $job->id;

                PublicationUsage::query()->firstOrCreate(
                    ['publication_job_id' => $job->id],
                    [
                        'user_id' => $user->id,
                        'platform' => $platform,
                        'is_free' => $platform === 'instagram' && $quote['free_instagram'],
                        'tokens_charged' => $cost,
                    ],
                );
            }

            if ($totalCharge > 0) {
                app(WalletService::class)->adjust(
                    $wallet->id,
                    -$totalCharge,
                    'publication',
                    'menfess:'.$menfess->id.':publication-debit',
                    Menfess::class,
                    $menfess->id,
                    'Reservasi publikasi: '.implode(', ', $menfess->platforms ?? []),
                );
            }

            return $publicationIds;
        }, attempts: 3);
    }

    public function instagramFreeAvailable(int $userId, ?Carbon $at = null): bool
    {
        $now = ($at ?? now())->copy()->timezone(config('app.timezone', 'Asia/Jakarta'));
        $quota = max(0, (int) config('menfess.instagram_free_posts_per_day', 2));

        if ($quota === 0 || $this->successfulFreeInstagramCount($userId, $now) >= $quota) {
            return false;
        }

        if (! $this->globalFreeAvailable($now)) {
            return false;
        }

        $hasActiveReservation = PublicationUsage::query()
            ->where('user_id', $userId)
            ->where('platform', 'instagram')
            ->where('is_free', true)
            ->whereNull('published_at')
            ->whereHas('publicationJob', fn ($query) => $query->whereIn('status', ['pending', 'scheduled', 'processing', 'needs_review']))
            ->exists();

        if ($hasActiveReservation) {
            return false;
        }

        $latest = PublicationUsage::query()
            ->where('user_id', $userId)
            ->where('platform', 'instagram')
            ->where('is_free', true)
            ->whereNotNull('published_at')
            ->latest('published_at')
            ->value('published_at');

        $cooldown = max(0, (int) config('menfess.instagram_free_cooldown_minutes', 60));

        return ! $latest || Carbon::parse($latest)->addMinutes($cooldown)->lessThanOrEqualTo($now);
    }

    public function globalFreeAvailable(?Carbon $at = null): bool
    {
        $now = ($at ?? now())->copy()->timezone(config('app.timezone', 'Asia/Jakarta'));
        $globalLimit = max(0, (int) config('menfess.global_free_posts_per_day', 10));

        if ($globalLimit === 0) {
            return false;
        }

        $usedCount = $this->successfulGlobalFreeCount($now);
        $activeReservations = $this->activeGlobalFreeReservations();

        return ($usedCount + $activeReservations) < $globalLimit;
    }

    public function successfulGlobalFreeCount(Carbon $now): int
    {
        return PublicationUsage::query()
            ->where('is_free', true)
            ->whereNotNull('published_at')
            ->where('published_at', '>=', $now->copy()->startOfDay())
            ->where('published_at', '<', $now->copy()->addDay()->startOfDay())
            ->count();
    }

    public function activeGlobalFreeReservations(): int
    {
        return PublicationUsage::query()
            ->where('is_free', true)
            ->whereNull('published_at')
            ->whereHas('publicationJob', fn ($query) => $query->whereIn('status', ['pending', 'scheduled', 'processing', 'needs_review']))
            ->count();
    }

    private function successfulFreeInstagramCount(int $userId, Carbon $now): int
    {
        return PublicationUsage::query()
            ->where('user_id', $userId)
            ->where('platform', 'instagram')
            ->where('is_free', true)
            ->whereNotNull('published_at')
            ->where('published_at', '>=', $now->copy()->startOfDay())
            ->where('published_at', '<', $now->copy()->addDay()->startOfDay())
            ->count();
    }
}
