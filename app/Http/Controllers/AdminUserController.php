<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Menfess;
use App\Models\User;
use App\Services\CloudinaryMediaStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $users = User::query()
            ->with('wallet')
            ->withCount('menfesses')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%');
            }))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'search'));
    }

    public function show(User $user): View
    {
        $user->load('wallet');

        return view('admin.users.show', [
            'user' => $user,
            'topups' => $user->paymentOrders()->with('package')->latest()->paginate(10, ['*'], 'topups'),
            'walletTransactions' => $user->wallet
                ? $user->wallet->transactions()->latest()->paginate(10, ['*'], 'wallet')
                : null,
            'menfesses' => $user->menfesses()->with(['category', 'publicationJobs'])->latest()->paginate(10, ['*'], 'menfesses'),
        ]);
    }

    public function destroyMenfess(Request $request, User $user, Menfess $menfess, CloudinaryMediaStorage $mediaStorage): RedirectResponse
    {
        Gate::authorize('delete', $menfess);
        abort_unless($menfess->user_id === $user->id, 404);

        $validated = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);
        $files = [];

        DB::transaction(function () use ($request, $user, $menfess, $validated, &$files): void {
            $locked = Menfess::query()->whereKey($menfess->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->user_id === $user->id, 404);

            $files = $locked->attachments()->get(['disk', 'path'])
                ->map(fn ($file): array => ['disk' => $file->disk, 'path' => $file->path, 'metadata' => []])
                ->concat($locked->generatedImages()->get(['disk', 'path', 'metadata'])
                    ->map(fn ($file): array => ['disk' => $file->disk, 'path' => $file->path, 'metadata' => $file->metadata ?? []]))
                ->all();

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'menfess.deleted_by_super_admin',
                'subject_type' => Menfess::class,
                'subject_id' => $locked->id,
                'metadata' => [
                    'owner_id' => $user->id,
                    'public_number' => $locked->public_number,
                    'previous_status' => $locked->status,
                    'reason' => $validated['reason'],
                ],
                'ip_address' => $request->ip(),
            ]);

            $locked->delete();
        });

        foreach ($files as $file) {
            try {
                if ($file['disk'] === 'cloudinary') {
                    $mediaStorage->delete($file['path'], $file['metadata']['delivery_type'] ?? 'upload');
                } else {
                    Storage::disk($file['disk'])->delete($file['path']);
                }
            } catch (\Throwable $exception) {
                Log::warning('Menfess file cleanup failed after administrative removal.', [
                    'menfess_id' => $menfess->id,
                    'disk' => $file['disk'],
                    'exception' => $exception::class,
                ]);
            }
        }

        return back()->with('notice', 'Menfess dihapus dan dicatat di audit log. Post yang sudah terbit di media sosial perlu dihapus terpisah melalui platform.');
    }
}
