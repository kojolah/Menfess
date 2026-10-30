<?php

namespace App\Http\Controllers;

use App\Models\Menfess;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MenfessController extends Controller
{
    public function index(): View
    {
        return view('menfesses.index', [
            'menfesses' => auth()->user()->menfesses()->with('category')->latest()->paginate(12),
        ]);
    }

    public function show(Menfess $menfess): View
    {
        Gate::authorize('view', $menfess);

        return view('menfesses.show', ['menfess' => $menfess->load('category', 'attachments', 'publicationJobs')]);
    }
}
