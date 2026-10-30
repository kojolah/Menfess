<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPublication;
use App\Models\AuditLog;
use App\Models\Menfess;
use App\Services\PublicationEligibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ModerationController extends Controller
{
    public function index(): View
    {
        return view('admin.moderation', [
            'menfesses' => Menfess::with(['category', 'attachments'])->where('status', 'moderation_pending')->oldest('submitted_at')->paginate(20),
        ]);
    }

    public function approve(Menfess $menfess): RedirectResponse
    {
        $publicationIds = DB::transaction(function () use ($menfess): array {
            $locked = Menfess::whereKey($menfess->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'moderation_pending', 409, 'Kiriman ini sudah diproses.');

            $locked->update(['status' => 'approved', 'reviewed_at' => now(), 'reviewed_by' => auth()->id()]);
            $publicationIds = app(PublicationEligibilityService::class)->reserveForMenfess($locked);
            AuditLog::create(['actor_id' => auth()->id(), 'action' => 'menfess.approved', 'subject_type' => Menfess::class, 'subject_id' => $locked->id]);

            return $publicationIds;
        });

        foreach ($publicationIds as $publicationId) {
            ProcessPublication::dispatch($publicationId)->afterCommit();
        }

        return back()->with('notice', 'Menfess disetujui dan masuk antrean publikasi.');
    }

    public function reject(Request $request, Menfess $menfess): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        DB::transaction(function () use ($menfess, $validated) {
            $locked = Menfess::whereKey($menfess->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'moderation_pending', 409, 'Kiriman ini sudah diproses.');

            $locked->update(['status' => 'rejected', 'rejection_reason' => $validated['reason'], 'reviewed_at' => now(), 'reviewed_by' => auth()->id()]);
            $locked->moderationActions()->create(['moderator_id' => auth()->id(), 'action' => 'rejected', 'reason' => $validated['reason']]);
            AuditLog::create(['actor_id' => auth()->id(), 'action' => 'menfess.rejected', 'subject_type' => Menfess::class, 'subject_id' => $locked->id]);
        });

        return back()->with('notice', 'Menfess ditolak. Pengirim dapat melihat alasan penolakan.');
    }
}
