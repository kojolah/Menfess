<?php

namespace App\Http\Controllers;

use App\Models\GeneratedImage;
use App\Models\Menfess;
use App\Models\Template;
use App\Services\CloudinaryMediaStorage;
use App\Services\MenfessImageRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GeneratedImageController extends Controller
{
    public function store(Request $request, Menfess $menfess, MenfessImageRenderer $renderer): RedirectResponse
    {
        Gate::authorize('view', $menfess);
        abort_unless(in_array($menfess->status, ['approved', 'published'], true), 404);
        $validated = $request->validate(['format' => ['required', Rule::in(['instagram_portrait', 'instagram_square', 'x_landscape'])]]);
        $platform = str_starts_with($validated['format'], 'x_') ? 'x' : 'instagram';
        $kind = $menfess->attachments()->exists() ? 'photo' : 'text';
        $template = Template::where('kind', $kind)->where('platform', $platform)->where('is_active', true)->first();

        try {
            $images = $renderer->persistFor($menfess, $validated['format'], $template);
        } catch (RuntimeException) {
            return back()->withErrors(['render' => 'Gambar tidak dapat dibuat. Periksa konfigurasi image renderer, font, dan Cloudinary.']);
        }

        return back()->with('notice', count($images).' gambar PNG berhasil dibuat.');
    }

    public function download(GeneratedImage $image, CloudinaryMediaStorage $mediaStorage): StreamedResponse
    {
        Gate::authorize('view', $image->menfess);
        abort_unless(in_array($image->menfess->status, ['approved', 'published'], true), 404);

        $contents = $mediaStorage->contents($image);

        return response()->streamDownload(static function () use ($contents): void {
            echo $contents;
        }, $image->menfess->public_number.'-'.$image->id.'.png', [
            'Content-Type' => 'image/png',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
