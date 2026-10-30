<?php

namespace App\Http\Controllers;

use App\Models\MenfessAttachment;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class MenfessAttachmentController extends Controller
{
    public function show(MenfessAttachment $attachment): Response
    {
        Gate::authorize('view', $attachment->menfess);

        $contents = Storage::disk($attachment->disk)->get($attachment->path);
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);
        abort_unless(in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return response($contents, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="menfess-attachment"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
