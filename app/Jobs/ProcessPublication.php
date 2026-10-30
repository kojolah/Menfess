<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\DailyGlobalQuota;
use App\Models\PublicationJob as Publication;
use App\Models\PublicationUsage;
use App\Models\Template;
use App\Models\Wallet;
use App\Services\MenfessImageRenderer;
use App\Services\Publishing\PublicationNeedsReview;
use App\Services\Publishing\PublicationService;
use App\Services\WalletService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessPublication implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $publicationId) {}

    public function handle(PublicationService $publisher): void
    {
        $publication = DB::transaction(function (): ?Publication {
            $publication = Publication::whereKey($this->publicationId)->lockForUpdate()->first();
            if (! $publication || ! in_array($publication->status, ['pending', 'scheduled'], true)) {
                return null;
            }
            if ($publication->status === 'scheduled' && $publication->scheduled_at?->isFuture()) {
                return null;
            }
            if ($publication->menfess()->value('status') !== 'approved') {
                $publication->update(['status' => 'cancelled', 'safe_error' => 'Menfess approval is no longer active.']);

                return null;
            }
            if ($publication->platform === 'instagram' && ! PublicationUsage::where('publication_job_id', $publication->id)->exists()) {
                $publication->update(['status' => 'needs_review', 'safe_error' => 'Instagram quota reservation is missing.']);

                return null;
            }

            $publication->update(['status' => 'processing', 'started_at' => now(), 'safe_error' => null]);

            return $publication->load(['menfess.category']);
        });

        if (! $publication) {
            return;
        }

        try {
            if (config('menfess.publisher_mode') === 'live') {
                $this->ensureRenderedImage($publication, app(MenfessImageRenderer::class));
            }
            $result = $publisher->publish($publication);
        } catch (PublicationNeedsReview $exception) {
            $this->markNeedsReview($publication, $exception->getMessage());

            return;
        } catch (\Throwable $exception) {
            Log::warning('Social publication outcome needs review.', [
                'publication_job_id' => $publication->id,
                'platform' => $publication->platform,
                'exception' => $exception::class,
                'message' => $this->sanitizeLogMessage($exception->getMessage()),
            ]);
            $this->markNeedsReview($publication, 'External platform did not confirm the result. Review before retrying.');

            return;
        }

        DB::transaction(function () use ($publication, $result): void {
            $locked = Publication::whereKey($publication->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'processing') {
                return;
            }
            $locked->update(['status' => 'published', 'published_at' => now(), 'external_post_id' => $result->externalPostId]);
            PublicationUsage::where('publication_job_id', $locked->id)->update(['published_at' => now()]);

            $usage = PublicationUsage::where('publication_job_id', $locked->id)->first();
            if ($usage && $usage->is_free) {
                $todayDate = now(config('app.timezone', 'Asia/Jakarta'))->format('Y-m-d');
                DailyGlobalQuota::query()->firstOrCreate(['date' => $todayDate], ['published_free_count' => 0])->increment('published_free_count');
            }

            AuditLog::create(['action' => 'publication.published', 'subject_type' => Publication::class, 'subject_id' => $locked->id]);

            $menfess = $locked->menfess()->lockForUpdate()->firstOrFail();
            $expectedJobs = count($menfess->platforms ?? []);
            $publishedJobs = $menfess->publicationJobs()->where('status', 'published')->count();
            if ($expectedJobs > 0 && $publishedJobs === $expectedJobs) {
                $menfess->update(['status' => 'published']);
            }
        });
    }

    private function ensureRenderedImage(Publication $publication, MenfessImageRenderer $renderer): void
    {
        $platform = $publication->platform;

        // X only renders a canvas card if the menfess actually has image attachments.
        // Pure text menfess on X is published directly as text without media.
        if ($platform === 'x' && ! $publication->menfess->attachments()->exists()) {
            return;
        }

        if ($publication->menfess->generatedImages()->where('platform', $platform)->exists()) {
            return;
        }

        $format = $platform === 'x' ? 'x_landscape' : 'instagram_portrait';
        $kind = $publication->menfess->attachments()->exists() ? 'photo' : 'text';
        $template = Template::query()->where('platform', $platform)->where('kind', $kind)->where('is_active', true)->latest('version')->first();
        $renderer->persistFor($publication->menfess, $format, $template);
        $publication->load('menfess.generatedImages');
    }

    private function sanitizeLogMessage(string $message): string
    {
        $message = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $message) ?? '';

        return mb_substr(trim($message), 0, 500);
    }

    private function markNeedsReview(Publication $publication, string $message): void
    {
        DB::transaction(function () use ($publication, $message): void {
            $locked = Publication::whereKey($publication->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'processing') {
                $locked->update(['status' => 'needs_review', 'safe_error' => mb_substr($message, 0, 500)]);
                if (str_contains($message, 'SIMULATION:')) {
                    PublicationUsage::where('publication_job_id', $locked->id)->delete();
                    if ($locked->tokens_reserved > 0) {
                        $wallet = Wallet::query()->where('user_id', $locked->menfess()->value('user_id'))->first();
                        if ($wallet) {
                            app(WalletService::class)->adjust(
                                $wallet->id,
                                (int) $locked->tokens_reserved,
                                'publication_refund',
                                'publication-simulation-refund:'.$locked->id,
                                Publication::class,
                                $locked->id,
                                'Refund publikasi mode simulasi; platform eksternal tidak dipanggil.',
                            );
                        }
                    }
                }
                AuditLog::create(['action' => 'publication.needs_review', 'subject_type' => Publication::class, 'subject_id' => $locked->id]);
            }
        });
    }
}
