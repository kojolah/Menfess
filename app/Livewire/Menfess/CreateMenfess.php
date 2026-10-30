<?php

namespace App\Livewire\Menfess;

use App\Jobs\ProcessPublication;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Menfess as MenfessModel;
use App\Services\MenfessAutoReviewService;
use App\Services\MenfessContentFilterResult;
use App\Services\MenfessContentFilterService;
use App\Services\MenfessImageRenderer;
use App\Services\PublicationEligibilityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

class CreateMenfess extends Component
{
    use WithFileUploads;

    public string $body = '';

    public ?int $categoryId = null;

    public array $platforms = ['instagram'];

    public string $format = 'instagram_portrait';

    public ?string $previewImage = null;

    public string $canvasBackground = '';

    public $image = null;

    public function mount(): void
    {
        $backgrounds = $this->configuredCanvasBackgrounds();
        $this->canvasBackground = $backgrounds[array_rand($backgrounds)];
    }

    public function updatedBody(): void
    {
        $this->previewImage = null;
    }

    public function updatedImage(): void
    {
        $this->previewImage = null;
    }

    public function buildPreview(MenfessImageRenderer $renderer): void
    {
        $validated = $this->validate([
            'body' => ['required', 'string', 'max:'.(int) config('menfess.max_characters', 1000)],
            'categoryId' => ['required', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => ['required', Rule::in(['instagram', 'x'])],
            'format' => ['required', Rule::in(['instagram_portrait', 'instagram_square', 'x_landscape'])],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $category = Category::findOrFail($validated['categoryId']);
        $sourcePath = $this->image?->store('preview-source', 'local');

        try {
            $this->previewImage = $renderer->preview(
                $validated['body'],
                $category,
                $validated['format'],
                $sourcePath,
                photoDisk: 'local',
                theme: ['background' => $this->safeCanvasBackground()],
            );
        } catch (\RuntimeException) {
            throw ValidationException::withMessages(['preview' => 'Preview gagal dibuat. Periksa konfigurasi image renderer, font, dan Cloudinary.']);
        } finally {
            if ($sourcePath) {
                Storage::disk('local')->delete($sourcePath);
            }
        }
    }

    public function saveDraft(): void
    {
        $this->save('draft');
    }

    public function submit(): void
    {
        $this->save('submit');
    }

    private function save(string $status): void
    {
        $maxCharacters = (int) config('menfess.max_characters', 1000);
        $validated = $this->validate([
            'body' => ['required', 'string', 'max:'.$maxCharacters],
            'categoryId' => ['required', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => ['required', Rule::in(['instagram', 'x'])],
            'format' => ['required', Rule::in(['instagram_portrait', 'instagram_square', 'x_landscape'])],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $isDraft = $status === 'draft';
        $contentFilter = $isDraft
            ? new MenfessContentFilterResult(trim($validated['body']), 0)
            : app(MenfessContentFilterService::class)->filter(trim($validated['body']));
        $reviewReasons = $isDraft ? [] : array_merge($contentFilter->reviewReasons, app(MenfessAutoReviewService::class)->reasons($contentFilter->body));
        if (! $isDraft && in_array('x', $validated['platforms'], true) && mb_strlen($contentFilter->body) > 280) {
            throw ValidationException::withMessages(['body' => 'Teks untuk X maksimal 280 karakter setelah penyensoran.']);
        }

        [$menfess, $publicationIds] = DB::transaction(function () use ($validated, $contentFilter, $reviewReasons, $isDraft) {
            $needsReview = ! $isDraft && $reviewReasons !== [];
            $menfessStatus = $isDraft ? 'draft' : ($needsReview ? 'moderation_pending' : 'approved');
            $menfess = MenfessModel::create([
                'user_id' => auth()->id(),
                'category_id' => $validated['categoryId'],
                'body' => $isDraft ? trim($validated['body']) : $contentFilter->body,
                'censored_count' => $isDraft ? 0 : $contentFilter->censoredCount,
                'filter_status' => $isDraft ? 'not_processed' : ($needsReview ? 'needs_review' : 'passed'),
                'filtered_at' => $isDraft ? null : now(),
                'platforms' => array_values(array_unique($validated['platforms'])),
                'status' => $menfessStatus,
                'submitted_at' => $isDraft ? null : now(),
                'metadata' => ['canvas_background' => $this->safeCanvasBackground()],
            ]);
            $menfess->update(['public_number' => 'MENFESS-'.str_pad((string) $menfess->id, 6, '0', STR_PAD_LEFT)]);

            if ($this->image) {
                $path = $this->image->store('menfess/'.$menfess->id, 'local');
                $menfess->attachments()->create([
                    'disk' => 'local',
                    'path' => $path,
                    'mime_type' => $this->image->getMimeType(),
                    'size_bytes' => $this->image->getSize(),
                ]);
            }

            $publicationIds = [];
            if (! $isDraft && ! $needsReview) {
                $publicationIds = app(PublicationEligibilityService::class)->reserveForMenfess($menfess);
                AuditLog::create(['actor_id' => auth()->id(), 'action' => 'menfess.auto_approved', 'subject_type' => MenfessModel::class, 'subject_id' => $menfess->id]);
            }

            return [$menfess, $publicationIds];
        });

        foreach ($publicationIds as $publicationId) {
            ProcessPublication::dispatch($publicationId)->afterCommit();
        }

        $notice = match (true) {
            $status === 'draft' => 'Draft tersimpan.',
            $menfess->filter_status === 'needs_review' => 'Menfess disensor dan menunggu pemeriksaan keamanan sebelum diterbitkan.',
            $contentFilter->censoredCount > 0 => 'Menfess disensor dan masuk antrean publikasi otomatis.',
            default => 'Menfess lolos pemeriksaan otomatis dan masuk antrean publikasi. Biaya token sudah dicatat sesuai platform.',
        };
        session()->flash('notice', $notice);
        $this->redirectRoute('menfesses.show', ['menfess' => $menfess->id], navigate: true);
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.menfess.create-menfess', [
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->get(),
            'maxCharacters' => (int) config('menfess.max_characters', 1000),
            'publicationQuote' => app(PublicationEligibilityService::class)->quote($user, $this->safePlatforms()),
            'canvasBackgroundColor' => $this->safeCanvasBackground(),
        ])->layout('layouts.app', ['title' => 'Kirim menfess']);
    }

    /** @return array<int, string> */
    private function configuredCanvasBackgrounds(): array
    {
        $backgrounds = array_values(array_filter(
            config('menfess.canvas_backgrounds', []),
            static fn ($value): bool => is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1,
        ));

        if ($backgrounds === []) {
            return [config('menfess.canvas_background', '#F6DDE8')];
        }

        return array_values(array_unique($backgrounds));
    }

    private function safeCanvasBackground(): string
    {
        $background = strtoupper($this->canvasBackground);

        return in_array($background, array_map('strtoupper', $this->configuredCanvasBackgrounds()), true)
            ? $background
            : $this->configuredCanvasBackgrounds()[0];
    }

    /** @return array<int, string> */
    private function safePlatforms(): array
    {
        return array_values(array_intersect(['instagram', 'x'], $this->platforms));
    }
}
