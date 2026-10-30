@extends('layouts.app')
@section('content')
<section class="mx-auto max-w-6xl px-5 py-10">
    <a href="{{ route('admin.users.index') }}" class="text-sm font-semibold text-[#a64e73]">← Daftar pengguna</a>
    <div class="mt-4 flex flex-wrap items-start justify-between gap-4 rounded-3xl border border-[#ece7e2] bg-white p-6 sm:p-8">
        <div><p class="text-sm font-bold uppercase tracking-[.16em] text-[#a64e73]">DETAIL AKUN</p><h1 class="mt-2 text-3xl font-bold">{{ $user->name }}</h1><p class="mt-2 text-slate-600">{{ $user->email }}</p><p class="mt-2 text-xs text-slate-500">Akun dibuat {{ $user->created_at->format('d M Y, H:i') }} · {{ str_replace('_', ' ', $user->role) }} · {{ $user->status }}</p></div>
        <div class="rounded-2xl bg-[#e7f1fa] px-5 py-4"><p class="text-xs font-semibold text-slate-600">Saldo wallet</p><p class="mt-1 text-2xl font-bold">{{ number_format($user->wallet?->balance ?? 0) }} <span class="text-sm">token</span></p></div>
    </div>

    <div class="mt-8 grid gap-8">
        <section class="rounded-3xl border border-[#ece7e2] bg-white p-6">
            <div class="flex flex-wrap items-end justify-between gap-2"><div><h2 class="text-xl font-bold">Riwayat top-up</h2><p class="mt-1 text-sm text-slate-500">Status pembayaran dan jumlah token dari order top-up QRIS.</p></div><span class="text-xs text-slate-500">{{ $topups->total() }} order</span></div>
            <div class="mt-4 overflow-x-auto"><table class="w-full min-w-[650px] text-left text-sm"><thead class="text-xs uppercase text-slate-500"><tr><th class="py-3 pr-4">Waktu</th><th class="py-3 pr-4">Order / Paket</th><th class="py-3 pr-4">Nominal</th><th class="py-3 pr-4">Token</th><th class="py-3">Status</th></tr></thead><tbody class="divide-y divide-slate-100">
                @forelse($topups as $order)<tr><td class="py-3 pr-4">{{ $order->paid_at?->format('d M Y, H:i') ?? $order->created_at->format('d M Y, H:i') }}</td><td class="py-3 pr-4"><span class="font-semibold">{{ $order->order_id }}</span><span class="mt-1 block text-xs text-slate-500">{{ $order->package?->name ?? 'Paket dihapus' }}</span></td><td class="py-3 pr-4">Rp{{ number_format($order->amount_idr, 0, ',', '.') }}</td><td class="py-3 pr-4">{{ number_format($order->tokens) }}</td><td class="py-3"><span class="rounded-full bg-[#fbf8f4] px-3 py-1 text-xs font-semibold">{{ str_replace('_', ' ', $order->status) }}</span></td></tr>
                @empty<tr><td colspan="5" class="py-8 text-center text-slate-500">Belum ada order top-up.</td></tr>@endforelse
            </tbody></table></div>
            <div class="mt-4">{{ $topups->links() }}</div>
        </section>

        <section class="rounded-3xl border border-[#ece7e2] bg-white p-6">
            <div class="flex flex-wrap items-end justify-between gap-2"><div><h2 class="text-xl font-bold">Mutasi wallet</h2><p class="mt-1 text-sm text-slate-500">Ledger token, termasuk top-up, pemakaian, dan refund.</p></div></div>
            @if($walletTransactions)<div class="mt-4 overflow-x-auto"><table class="w-full min-w-[680px] text-left text-sm"><thead class="text-xs uppercase text-slate-500"><tr><th class="py-3 pr-4">Waktu</th><th class="py-3 pr-4">Jenis / Alasan</th><th class="py-3 pr-4">Perubahan</th><th class="py-3 pr-4">Saldo sebelum</th><th class="py-3">Saldo sesudah</th></tr></thead><tbody class="divide-y divide-slate-100">
                @forelse($walletTransactions as $transaction)<tr><td class="py-3 pr-4">{{ $transaction->created_at->format('d M Y, H:i') }}</td><td class="py-3 pr-4"><span class="font-semibold">{{ str_replace('_', ' ', $transaction->type) }}</span><span class="mt-1 block text-xs text-slate-500">{{ $transaction->reason ?? '—' }}</span></td><td class="py-3 pr-4 font-semibold {{ $transaction->amount < 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $transaction->amount > 0 ? '+' : '' }}{{ number_format($transaction->amount) }}</td><td class="py-3 pr-4">{{ number_format($transaction->balance_before) }}</td><td class="py-3">{{ number_format($transaction->balance_after) }}</td></tr>
                @empty<tr><td colspan="5" class="py-8 text-center text-slate-500">Belum ada mutasi wallet.</td></tr>@endforelse
            </tbody></table></div><div class="mt-4">{{ $walletTransactions->links() }}</div>@else<p class="mt-5 rounded-2xl bg-[#fbf8f4] p-5 text-sm text-slate-500">Akun ini belum memiliki wallet.</p>@endif
        </section>

        <section class="rounded-3xl border border-[#ece7e2] bg-white p-6">
            <div class="flex flex-wrap items-end justify-between gap-2"><div><h2 class="text-xl font-bold">Riwayat menfess</h2><p class="mt-1 text-sm text-slate-500">Konten yang ditulis akun ini beserta status tiap platform.</p></div><span class="text-xs text-slate-500">{{ $menfesses->total() }} kiriman</span></div>
            <div class="mt-4 divide-y divide-slate-100">
                @forelse($menfesses as $menfess)
                    <article class="py-5"><div class="flex flex-wrap items-start justify-between gap-3"><div><p class="font-bold">{{ $menfess->public_number ?? 'Draft' }} <span class="font-medium text-slate-500">· {{ $menfess->category->name }}</span></p><p class="mt-1 text-xs text-slate-500">{{ $menfess->created_at->format('d M Y, H:i') }} · {{ str_replace('_', ' ', $menfess->status) }} · {{ implode(', ', $menfess->platforms ?? []) }}</p></div><a href="{{ route('menfesses.show', $menfess) }}" class="text-sm font-semibold text-[#a64e73]">Buka detail</a></div>
                        <p class="mt-3 whitespace-pre-wrap break-words text-sm leading-6 text-slate-700">{{ $menfess->body }}</p>
                        @if($menfess->publicationJobs->isNotEmpty())<div class="mt-3 flex flex-wrap gap-2">@foreach($menfess->publicationJobs as $job)<span class="rounded-full bg-[#e7f1fa] px-3 py-1 text-xs font-semibold">{{ strtoupper($job->platform) }} · {{ str_replace('_', ' ', $job->status) }} @if($job->external_post_id) · {{ $job->external_post_id }} @endif</span>@endforeach</div>@endif
                        <details class="mt-4 rounded-2xl border border-rose-100 bg-rose-50/60 p-4"><summary class="cursor-pointer text-sm font-bold text-rose-800">Hapus menfess karena melanggar aturan</summary><form method="POST" action="{{ route('admin.users.menfesses.destroy', [$user, $menfess]) }}" class="mt-4 grid gap-3">@csrf @method('DELETE')<label class="text-sm font-semibold">Alasan penghapusan<textarea name="reason" required minlength="10" maxlength="1000" rows="2" class="mt-2 w-full rounded-xl border-rose-200" placeholder="Jelaskan aturan yang dilanggar"></textarea></label><label class="flex items-start gap-2 text-xs leading-5 text-rose-900"><input type="checkbox" required class="mt-1 rounded border-rose-300"><span>Saya memahami penghapusan ini permanen dan post yang sudah terbit di media sosial tidak ikut terhapus.</span></label><button class="justify-self-start rounded-full bg-rose-700 px-4 py-2.5 text-sm font-bold text-white">Hapus kiriman ini</button></form></details>
                    </article>
                @empty<p class="py-10 text-center text-slate-500">Akun ini belum menulis menfess.</p>@endforelse
            </div>
            <div class="mt-4">{{ $menfesses->links() }}</div>
        </section>
    </div>
</section>
@endsection
