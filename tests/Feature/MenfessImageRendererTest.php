<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Menfess;
use App\Models\User;
use App\Services\ImagickMenfessImageRenderer;
use App\Services\MenfessImageRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MenfessImageRendererTest extends TestCase
{
    use RefreshDatabase;

    public function test_renderer_creates_a_sharp_instagram_portrait_png(): void
    {
        config(['menfess.image_renderer' => 'playwright']);
        Storage::fake('public');
        $this->fakeCloudinary();
        $category = $this->createCategory();

        $preview = app(MenfessImageRenderer::class)->preview('Semangat untuk minggu ujian!', $category, 'instagram_portrait');

        $image = base64_decode($preview, true);
        $dimensions = getimagesizefromstring($image);
        $this->assertSame(1080, $dimensions[0]);
        $this->assertSame(1350, $dimensions[1]);
        $this->assertSame([], Storage::disk('public')->allFiles());
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/image/upload') && $this->formValue($request, 'type') === 'authenticated');
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/image/destroy') && $this->formValue($request, 'type') === 'authenticated');
    }

    public function test_renderer_splits_a_long_menfess_into_numbered_png_slides(): void
    {
        config(['menfess.image_renderer' => 'playwright']);
        Storage::fake('public');
        $this->fakeCloudinary();
        $menfess = Menfess::create([
            'user_id' => User::factory()->create()->id,
            'category_id' => $this->createCategory()->id,
            'body' => implode(' ', array_fill(0, 12, 'Kalimat kampus yang cukup panjang untuk dibagi secara wajar.')),
            'platforms' => ['instagram'],
            'status' => 'approved',
            'public_number' => 'MENFESS-000001',
            'metadata' => ['canvas_background' => '#DCEBFA'],
        ]);

        $images = app(MenfessImageRenderer::class)->persistFor($menfess, 'instagram_square');

        $this->assertGreaterThan(1, count($images));
        $this->assertSame(count($images), $images[0]['metadata']['slides']);
        $this->assertSame(1, $images[0]['metadata']['slide']);
        $this->assertSame('image/png', $images[0]['mime_type']);
        $this->assertSame('cloudinary', $images[0]['disk']);
        $this->assertSame('#DCEBFA', $images[0]['metadata']['canvas_background']);
        $this->assertStringStartsWith('https://res.cloudinary.com/test-cloud/image/upload/', $images[0]['metadata']['secure_url']);
        $this->assertSame([], Storage::disk('public')->allFiles());
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/image/upload') && $this->formValue($request, 'type') === 'upload');
    }

    public function test_imagick_preview_handles_short_long_unicode_and_jpeg_output_at_fixed_portrait_size(): void
    {
        if (! extension_loaded('imagick')) {
            $this->markTestSkipped('PHP Imagick is required for raster integration coverage.');
        }

        config(['menfess.image_renderer' => 'imagick']);
        $this->fakeCloudinary();
        $category = $this->createCategory();

        foreach ([
            'Hai!',
            'Pesan kampus dengan panjang normal untuk memastikan layout tetap seimbang.',
            implode(' ', array_fill(0, 70, 'Pesan panjang berisi teks yang harus dibungkus dengan aman.')),
            'Emoji dan unicode: ✨ café, 東京, dan tanda baca — aman?',
        ] as $message) {
            $preview = app(MenfessImageRenderer::class)->preview(
                $message,
                $category,
                'instagram_portrait',
                theme: ['background' => '#DCEBFA'],
            );
            $image = base64_decode($preview, true);
            $dimensions = getimagesizefromstring($image);

            $this->assertSame(1080, $dimensions[0]);
            $this->assertSame(1350, $dimensions[1]);
        }

        $png = base64_decode($preview, true);
        $jpeg = app(ImagickMenfessImageRenderer::class)->jpegFromPng($png);
        $jpegInfo = getimagesizefromstring($jpeg);
        $this->assertSame(IMAGETYPE_JPEG, $jpegInfo[2]);
    }

    public function test_imagick_renderer_preserves_saved_background_for_final_render(): void
    {
        if (! extension_loaded('imagick')) {
            $this->markTestSkipped('PHP Imagick is required for raster integration coverage.');
        }

        config(['menfess.image_renderer' => 'imagick']);
        Storage::fake('public');
        $this->fakeCloudinary();
        $menfess = Menfess::create([
            'user_id' => User::factory()->create()->id,
            'category_id' => $this->createCategory()->id,
            'body' => 'Background harus tetap sama pada render final.',
            'platforms' => ['instagram'],
            'status' => 'approved',
            'public_number' => 'MENFESS-000001',
            'metadata' => ['canvas_background' => '#DCEBFA'],
        ]);

        $images = app(MenfessImageRenderer::class)->persistFor($menfess, 'instagram_portrait');

        $this->assertSame(1080, $images[0]['width']);
        $this->assertSame(1350, $images[0]['height']);
        $this->assertSame('#DCEBFA', $images[0]['metadata']['canvas_background']);
        $this->assertSame('imagick', $images[0]['metadata']['renderer']);
    }

    public function test_imagick_renderer_crops_uploaded_photos_without_changing_portrait_dimensions(): void
    {
        if (! extension_loaded('imagick')) {
            $this->markTestSkipped('PHP Imagick is required for raster integration coverage.');
        }

        config(['menfess.image_renderer' => 'imagick']);
        Storage::fake('local');
        $this->fakeCloudinary();
        $photoPath = UploadedFile::fake()->image('campus.jpg', 1200, 600)->store('menfess/photo', 'local');
        $menfess = Menfess::create([
            'user_id' => User::factory()->create()->id,
            'category_id' => $this->createCategory()->id,
            'body' => 'Foto landscape dipotong rapi.',
            'platforms' => ['instagram'],
            'status' => 'approved',
            'public_number' => 'MENFESS-000002',
            'metadata' => ['canvas_background' => '#DDF2E5'],
        ]);
        $menfess->attachments()->create([
            'disk' => 'local',
            'path' => $photoPath,
            'mime_type' => 'image/jpeg',
            'size_bytes' => Storage::disk('local')->size($photoPath),
        ]);

        $images = app(MenfessImageRenderer::class)->persistFor($menfess, 'instagram_portrait');

        $this->assertSame(1080, $images[0]['width']);
        $this->assertSame(1350, $images[0]['height']);
    }

    private function fakeCloudinary(): void
    {
        config([
            'services.cloudinary.cloud_name' => 'test-cloud',
            'services.cloudinary.api_key' => 'test-api-key',
            'services.cloudinary.api_secret' => 'test-api-secret',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'api.cloudinary.com/*' => function (Request $request) {
                if (str_ends_with($request->url(), '/image/destroy')) {
                    return Http::response(['result' => 'ok']);
                }

                $publicId = $this->formValue($request, 'public_id') ?? '';

                return Http::response([
                    'public_id' => $publicId,
                    'secure_url' => 'https://res.cloudinary.com/test-cloud/image/upload/v1/'.$publicId.'.png',
                    'bytes' => 1024,
                ]);
            },
        ]);
    }

    private function formValue(Request $request, string $name): ?string
    {
        $data = $request->data();
        if (! array_is_list($data)) {
            return isset($data[$name]) ? (string) $data[$name] : null;
        }

        $part = collect($data)->firstWhere('name', $name);

        return isset($part['contents']) ? (string) $part['contents'] : null;
    }

    private function createCategory(): Category
    {
        return Category::create([
            'name' => 'umum!',
            'slug' => 'umum',
            'badge_color' => '#F7ECCD',
            'is_active' => true,
        ]);
    }
}
