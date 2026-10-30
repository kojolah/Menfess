@extends('layouts.app')
@section('content')
<section class="mx-auto max-w-6xl px-5 py-10">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div><p class="text-sm font-bold uppercase tracking-[.18em] text-[#a64e73]">RUANG PENGELOLA</p><h1 class="mt-2 text-3xl font-bold">Ringkasan operasional</h1><p class="mt-2 text-slate-600">Status antrean, publikasi, dan pembayaran terverifikasi.</p></div>
        <span class="rounded-full bg-[#e2f1e7] px-4 py-2 text-sm font-bold">{{ config('menfess.publisher_mode') === 'live' ? 'Mode API live' : 'Mode simulasi' }}</span>
    </div>
    @if(config('menfess.publisher_mode') !== 'live')<div class="mt-6 rounded-2xl border border-[#f0d8a6] bg-[#fff8e8] p-4 text-sm text-[#6f5420]">Simulasi aktif. Job tidak membuat post di Instagram atau X; hasil akan masuk status <strong>needs review</strong>.</div>@endif
    <div class="mt-7 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach([['Pengguna', $metrics['users'], 'akun terdaftar', '#e7f1fa'], ['Menfess', $metrics['menfesses'], 'semua status', '#f8e7ef'], ['Moderasi', $metrics['moderation'], 'menunggu tinjauan', '#fff3db'], ['Publikasi', $metrics['published'], 'berhasil dikonfirmasi', '#e2f1e7'], ['Perlu perhatian', $metrics['failed'], 'gagal atau needs review', '#fff0ed'], ['Pembayaran', $metrics['payments'], 'top-up terverifikasi', '#ece7f7'], ['Token dipakai', number_format($metrics['tokens_used']), 'token tercatat keluar', '#e7f1fa'], ['Top-up terverifikasi', 'Rp'.number_format($metrics['verified_topups_idr'], 0, ',', '.'), 'nilai transaksi, bukan laba', '#f7e9d9']] as [$label, $value, $hint, $tone])
            <article class="rounded-3xl border border-[#ece7e2] bg-white p-5"><p class="text-sm font-semibold text-slate-500">{{ $label }}</p><p class="mt-3 text-2xl font-bold" style="color:#182a4b">{{ $value }}</p><p class="mt-1 text-xs text-slate-500">{{ $hint }}</p></article>
        @endforeach
    </div>
    <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_1.3fr]">
        <article class="rounded-3xl border border-[#ece7e2] bg-white p-6"><h2 class="text-lg font-bold">Ruang kerja</h2><div class="mt-4 grid gap-3"><a class="rounded-2xl bg-[#f8e7ef] p-4 font-semibold" href="{{ route('admin.moderation.index') }}">Moderation queue <span class="float-right">→</span></a><a class="rounded-2xl bg-[#e7f1fa] p-4 font-semibold" href="{{ route('admin.publications.index') }}">Publication center <span class="float-right">→</span></a>@if(auth()->user()->hasRole(\App\UserRole::SuperAdmin))<a class="rounded-2xl bg-[#e2f1e7] p-4 font-semibold" href="{{ route('admin.content-filters.index') }}">Filter konten otomatis <span class="float-right">→</span></a><a class="rounded-2xl bg-[#f7e9d9] p-4 font-semibold" href="{{ route('admin.users.index') }}">Pengguna dan riwayat <span class="float-right">→</span></a>@endif</div><p class="mt-4 text-xs leading-5 text-slate-500">Top-up terverifikasi menunjukkan nilai pembayaran bruto; angka ini bukan perhitungan laba bersih.</p></article>
        <article class="rounded-3xl border border-[#ece7e2] bg-white p-6"><h2 class="text-lg font-bold">Aktivitas publikasi terbaru</h2><div class="mt-4 divide-y divide-slate-100">@forelse($recentPublications as $job)<div class="flex items-center justify-between gap-4 py-3 text-sm"><div><p class="font-semibold">{{ $job->menfess->public_number }} · {{ strtoupper($job->platform) }}</p><p class="mt-1 text-xs text-slate-500">{{ $job->created_at->diffForHumans() }}</p></div><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold">{{ str_replace('_', ' ', $job->status) }}</span></div>@empty<p class="py-8 text-sm text-slate-500">Belum ada aktivitas publikasi.</p>@endforelse</div></article>
    </div>
</section>
@endsection
