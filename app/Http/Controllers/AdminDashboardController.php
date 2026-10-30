<?php

namespace App\Http\Controllers;

use App\Models\Menfess;
use App\Models\PaymentOrder;
use App\Models\PublicationJob;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'metrics' => [
                'users' => User::count(),
                'menfesses' => Menfess::count(),
                'moderation' => Menfess::where('status', 'moderation_pending')->count(),
                'published' => PublicationJob::where('status', 'published')->count(),
                'failed' => PublicationJob::whereIn('status', ['failed', 'needs_review'])->count(),
                'payments' => PaymentOrder::where('status', 'paid')->count(),
                'tokens_used' => WalletTransaction::where('type', 'publication')->where('amount', '<', 0)->sum('amount') * -1,
                'verified_topups_idr' => PaymentOrder::where('status', 'paid')->sum('amount_idr'),
            ],
            'recentPublications' => PublicationJob::with('menfess')->latest()->limit(6)->get(),
        ]);
    }
}
