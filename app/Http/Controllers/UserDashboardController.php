<?php

namespace App\Http\Controllers;

use App\Models\PublicationJob;
use App\Services\PublicationEligibilityService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $statusCounts = $user->menfesses()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view('dashboard', [
            'user' => $user,
            'menfessCount' => (int) $statusCounts->sum(),
            'moderationCount' => (int) $statusCounts->get('moderation_pending', 0),
            'walletBalance' => (int) ($user->wallet?->balance ?? 0),
            'publicationQuote' => app(PublicationEligibilityService::class)->quote($user, ['instagram']),
            'latestPublication' => PublicationJob::query()
                ->whereHas('menfess', fn ($query) => $query->where('user_id', $user->id))
                ->latest('created_at')
                ->first(),
            'recentTransactions' => $user->wallet?->transactions()->latest()->limit(5)->get() ?? collect(),
            'recentMenfesses' => $user->menfesses()->with('category')->latest()->limit(5)->get(),
        ]);
    }
}
