<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPublication;
use App\Models\PublicationJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PublicationController extends Controller
{
    public function index(): View
    {
        return view('admin.publications', [
            'publications' => PublicationJob::with(['menfess.category'])->latest()->paginate(20),
        ]);
    }

    public function schedule(Request $request, PublicationJob $publication): RedirectResponse
    {
        $validated = $request->validate(['scheduled_at' => ['required', 'date', 'after:now']]);
        abort_unless(in_array($publication->status, ['pending', 'scheduled'], true), 409);

        $scheduledAt = Carbon::parse($validated['scheduled_at']);
        $publication->update(['status' => 'scheduled', 'scheduled_at' => $scheduledAt]);
        ProcessPublication::dispatch($publication->id)->delay($scheduledAt);

        return back()->with('notice', 'Publikasi dijadwalkan. Mode proses: '.config('menfess.publisher_mode').'.');
    }
}
