@extends('layouts.app')

@section('content')
    <section class="mx-auto max-w-7xl px-4 py-7 sm:px-6 sm:py-10 lg:px-8">
        <div class="relative isolate overflow-hidden rounded-[2rem] bg-gradient-to-br from-[#0b2d59] via-[#174b87] to-[#2467a8] px-5 py-6 text-white shadow-[0_20px_60px_rgba(11,45,89,.16)] sm:px-8 sm:py-8 lg:px-10">
            <div class="pointer-events-none absolute -right-8 -top-14 -z-10 size-48 rounded-full border-[20px] border-white/5 sm:right-12 sm:size-64"></div>
            <div class="pointer-events-none absolute bottom-0 right-1/4 -z-10 size-20 translate-y-1/2 rounded-full bg-[#ffc629]/15 sm:size-28"></div>
            <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                <div class="max-w-2xl">
                    <div class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-extrabold tracking-wide text-[#ffe08a]"><span class="size-2 rounded-full bg-[#ffc629]"></span> YOUR VOICE, YOUR SPACE.</div>
                    <h1 class="mt-4 font-heading text-3xl leading-tight font-extrabold tracking-tight sm:text-4xl">Hai, {{ \Illuminate\Support\Str::of($user->name)->explode(' ')->first() }}!</h1>
                    <p class="mt-2 max-w-xl text-sm leading-6 text-blue-100 sm:text-base sm:leading-7">Selamat datang di ruang ceritamu. Bagikan cerita, sebarkan informasi, dan tetap terhubung dengan komunitas Warmadewa.</p>
                </div>
                <div class="flex items-center gap-3 self-start rounded-2xl border border-white/20 bg-white/10 p-3 sm:self-center sm:p-4">
                    <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-white p-1.5 shadow-sm"><img src="{{ asset('logo.png') }}" alt="Logo Unwarfess" class="size-full rounded-xl object-cover"></span>
                    <div><p class="font-heading text-sm font-extrabold text-white">Komunitas Warmadewa</p><p class="mt-0.5 text-xs font-semibold text-blue-100">Instagram · X</p></div>
                </div>
            </div>
        </div>

        <section aria-labelledby="quick-actions-title" class="mt-8">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div><p class="text-xs font-extrabold tracking-[.16em] text-[#174b87]">MULAI DARI SINI</p><h2 id="quick-actions-title" class="mt-1 font-heading text-xl font-extrabold text-[#0b2d59] sm:text-2xl">Akses cepat</h2></div>
                <p class="text-sm font-semibold text-[#58718e]">Semua yang kamu butuhkan, dekat.</p>
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <a href="{{ route('menfesses.create') }}" class="group flex min-h-24 items-center gap-4 rounded-[1.4rem] border border-[#dceefa] bg-white p-4 shadow-[0_8px_30px_rgba(11,45,89,.04)] transition hover:-translate-y-0.5 hover:border-[#174b87]/30 hover:shadow-[0_14px_35px_rgba(11,45,89,.09)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#174b87] sm:p-5">
                    <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-[#ffc629] text-[#0b2d59] transition group-hover:rotate-3" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" /></svg></span>
                    <span class="min-w-0 flex-1"><strong class="block font-heading font-extrabold text-[#0b2d59]">Buat menfess</strong><span class="mt-1 block text-sm leading-5 text-[#58718e]">Tulis dan pilih kanal tujuan.</span></span><span class="text-xl font-bold text-[#174b87] transition group-hover:translate-x-1" aria-hidden="true">→</span>
                </a>
                <a href="{{ route('menfesses.index') }}" class="group flex min-h-24 items-center gap-4 rounded-[1.4rem] border border-[#dceefa] bg-white p-4 shadow-[0_8px_30px_rgba(11,45,89,.04)] transition hover:-translate-y-0.5 hover:border-[#174b87]/30 hover:shadow-[0_14px_35px_rgba(11,45,89,.09)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#174b87] sm:p-5">
                    <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-[#dceefa] text-[#0b2d59] transition group-hover:rotate-3" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" /></svg></span>
                    <span class="min-w-0 flex-1"><strong class="block font-heading font-extrabold text-[#0b2d59]">Riwayat menfess</strong><span class="mt-1 block text-sm leading-5 text-[#58718e]">Lihat status semua kirimanmu.</span></span><span class="text-xl font-bold text-[#174b87] transition group-hover:translate-x-1" aria-hidden="true">→</span>
                </a>
                <a href="{{ route('wallet.index') }}" class="group flex min-h-24 items-center gap-4 rounded-[1.4rem] border border-[#dceefa] bg-white p-4 shadow-[0_8px_30px_rgba(11,45,89,.04)] transition hover:-translate-y-0.5 hover:border-[#174b87]/30 hover:shadow-[0_14px_35px_rgba(11,45,89,.09)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#174b87] sm:p-5">
                    <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-[#fff2c8] text-[#8b6500] transition group-hover:rotate-3" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="3" /><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18m-5 5h2" /></svg></span>
                    <span class="min-w-0 flex-1"><strong class="block font-heading font-extrabold text-[#0b2d59]">Wallet & token</strong><span class="mt-1 block text-sm leading-5 text-[#58718e]">Cek saldo dan riwayat transaksi.</span></span><span class="text-xl font-bold text-[#174b87] transition group-hover:translate-x-1" aria-hidden="true">→</span>
                </a>
            </div>
        </section>

        <section aria-labelledby="your-stats-title" class="mt-9">
            <div><p class="text-xs font-extrabold tracking-[.16em] text-[#174b87]">RINGKASAN AKUN</p><h2 id="your-stats-title" class="mt-1 font-heading text-xl font-extrabold text-[#0b2d59] sm:text-2xl">Aktivitasmu</h2></div>
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                <article class="min-w-0 rounded-[1.35rem] border border-[#dceefa] bg-white p-4 shadow-[0_8px_30px_rgba(11,45,89,.04)] sm:p-5"><span class="grid size-10 place-items-center rounded-xl bg-[#dceefa] text-[#0b2d59]" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M5 5h14v14H5zM8 9h8M8 13h8M8 17h5" /></svg></span><p class="mt-4 text-xs leading-5 font-bold text-[#58718e] sm:text-sm">Total kiriman</p><p class="mt-1 font-heading text-2xl font-extrabold text-[#0b2d59] sm:text-3xl">{{ number_format($menfessCount) }}</p><p class="mt-1 text-xs font-semibold text-[#58718e]">Semua status</p></article>
                <article class="min-w-0 rounded-[1.35rem] border border-[#dceefa] bg-white p-4 shadow-[0_8px_30px_rgba(11,45,89,.04)] sm:p-5"><span class="grid size-10 place-items-center rounded-xl bg-[#fce5ef] text-[#9b3462]" aria-hidden="true"><span class="font-heading text-sm font-black">IG</span></span><p class="mt-4 text-xs leading-5 font-bold text-[#58718e] sm:text-sm">Menfess ke Instagram</p><p class="mt-1 font-heading text-2xl font-extrabold text-[#0b2d59] sm:text-3xl">{{ number_format($instagramCount) }}</p><p class="mt-1 text-xs font-semibold text-[#58718e]">Tujuan dipilih</p></article>
                <article class="min-w-0 rounded-[1.35rem] border border-[#dceefa] bg-white p-4 shadow-[0_8px_30px_rgba(11,45,89,.04)] sm:p-5"><span class="grid size-10 place-items-center rounded-xl bg-[#e8eaf0] text-[#0b2d59]" aria-hidden="true"><span class="font-heading text-sm font-black">X</span></span><p class="mt-4 text-xs leading-5 font-bold text-[#58718e] sm:text-sm">Menfess ke X</p><p class="mt-1 font-heading text-2xl font-extrabold text-[#0b2d59] sm:text-3xl">{{ number_format($xCount) }}</p><p class="mt-1 text-xs font-semibold text-[#58718e]">Tujuan dipilih</p></article>
                <article class="min-w-0 rounded-[1.35rem] border border-[#f0dfaa] bg-[#fff9e8] p-4 shadow-[0_8px_30px_rgba(11,45,89,.04)] sm:p-5"><span class="grid size-10 place-items-center rounded-xl bg-[#ffc629] text-[#0b2d59]" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l2.5 2.5M20 12a8 8 0 1 1-2.35-5.65M20 4v5h-5" /></svg></span><p class="mt-4 text-xs leading-5 font-bold text-[#58718e] sm:text-sm">Gratis Instagram</p><p class="mt-1 font-heading text-2xl font-extrabold text-[#0b2d59] sm:text-3xl">{{ $publicationQuote['used_today'] }}/{{ $publicationQuote['daily_limit'] }}</p><p class="mt-1 text-xs font-semibold text-[#58718e]">{{ $publicationQuote['remaining_today'] }} tersisa hari ini</p>@if ($publicationQuote['next_free_at'])<p class="mt-2 text-xs leading-5 font-bold text-[#8b6500]">Berikutnya {{ $publicationQuote['next_free_at']->timezone(config('app.timezone', 'Asia/Jakarta'))->format('H:i') }} WIB</p>@endif</article>
                <a href="{{ route('wallet.index') }}" class="group min-w-0 rounded-[1.35rem] border border-[#c9e4d1] bg-[#eff8f1] p-4 shadow-[0_8px_30px_rgba(11,45,89,.04)] transition hover:-translate-y-0.5 hover:border-[#78b78d] sm:p-5"><span class="grid size-10 place-items-center rounded-xl bg-white text-[#17653a]" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 8.5c-.7-.6-1.6-.9-2.7-.9-1.7 0-2.8.8-2.8 2 0 3.2 5.7 1.4 5.7 4.5 0 1.2-1.1 2.2-2.9 2.2-1.2 0-2.3-.4-3.1-1.2M12 6v12" /></svg></span><p class="mt-4 text-xs leading-5 font-bold text-[#58718e] sm:text-sm">Saldo token</p><p class="mt-1 font-heading text-2xl font-extrabold text-[#0b2d59] sm:text-3xl">{{ number_format($walletBalance) }}</p><p class="mt-1 truncate text-xs font-semibold text-[#17653a]">Token · top up →</p><p class="mt-2 text-[11px] leading-4 font-semibold text-[#58718e]">IG {{ $tokenCosts['instagram'] ?? 2 }} · X {{ $tokenCosts['x'] ?? 1 }} token jika berbayar</p></a>
            </div>
        </section>

        <div class="mt-9 grid gap-5 lg:grid-cols-[1.35fr_.65fr]">
            <section aria-labelledby="recent-menfess-title" class="min-w-0">
                <div class="flex items-end justify-between gap-3">
                    <div><p class="text-xs font-extrabold tracking-[.16em] text-[#174b87]">PERJALANAN KIRIMAN</p><h2 id="recent-menfess-title" class="mt-1 font-heading text-xl font-extrabold text-[#0b2d59] sm:text-2xl">Menfess terbaru</h2></div>
                    <a href="{{ route('menfesses.index') }}" class="shrink-0 rounded-lg px-2 py-2 text-sm font-extrabold text-[#174b87] underline decoration-[#ffc629] decoration-2 underline-offset-4 hover:text-[#0b2d59]">Lihat semua</a>
                </div>
                <div class="mt-4 overflow-hidden rounded-[1.5rem] border border-[#dceefa] bg-white shadow-[0_10px_35px_rgba(11,45,89,.05)]">
                    @forelse ($recentMenfesses as $menfess)
                        <article class="border-b border-[#e8f1f8] p-4 last:border-0 sm:p-5">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="min-w-0"><p class="truncate font-heading text-sm font-extrabold text-[#0b2d59]">{{ $menfess->public_number ?? 'Draft' }} <span class="font-sans font-semibold text-[#8ba0b7]">·</span> <span class="font-sans font-bold text-[#58718e]">{{ $menfess->category?->name ?? 'Tanpa kategori' }}</span></p><p class="mt-1 text-xs font-semibold text-[#7890aa]">{{ $menfess->created_at?->timezone(config('app.timezone', 'Asia/Jakarta'))->format('d M Y · H:i') }} WIB</p></div>
                                <span class="rounded-full bg-[#dceefa] px-3 py-1 text-xs font-extrabold capitalize text-[#174b87]">{{ str_replace('_', ' ', $menfess->status) }}</span>
                            </div>
                            <p class="mt-3 break-words text-sm leading-6 text-[#315276]">{{ \Illuminate\Support\Str::limit($menfess->body, 180) }}</p>
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                @forelse ($menfess->publicationJobs as $job)
                                    @php
                                        $publicationTone = match ($job->status) {
                                            'published' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
                                            'failed', 'needs_review' => 'bg-rose-50 text-rose-800 ring-rose-200',
                                            'cancelled' => 'bg-slate-100 text-slate-600 ring-slate-200',
                                            default => 'bg-amber-50 text-amber-800 ring-amber-200',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-extrabold ring-1 ring-inset {{ $publicationTone }}"><span>{{ strtoupper($job->platform) }}</span><span aria-hidden="true">·</span><span class="capitalize">{{ str_replace('_', ' ', $job->status) }}</span></span>
                                @empty
                                    <span class="text-xs font-semibold text-[#7890aa]">Belum ada job publikasi</span>
                                @endforelse
                                <a href="{{ route('menfesses.show', $menfess) }}" class="ml-auto rounded-lg px-2 py-1 text-xs font-extrabold text-[#174b87] hover:bg-[#dceefa]">Detail <span aria-hidden="true">→</span></a>
                            </div>
                        </article>
                    @empty
                        <div class="px-5 py-10 text-center sm:px-8">
                            <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-[#dceefa] text-[#0b2d59]" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h8m-8 4h5m-9 6 1.8-4.5A8 8 0 1 1 8 19.5L4 20Z" /></svg></span>
                            <h3 class="mt-4 font-heading text-lg font-extrabold text-[#0b2d59]">No stories shared yet.</h3>
                            <p class="mx-auto mt-2 max-w-sm text-sm leading-6 text-[#58718e]">Belum ada cerita yang kamu bagikan. Mulai kirim pikiranmu lewat Unwarfess.</p>
                            <a href="{{ route('menfesses.create') }}" class="mt-5 inline-flex min-h-11 items-center justify-center rounded-xl bg-[#ffc629] px-5 py-2.5 font-extrabold text-[#0b2d59] transition hover:bg-[#ffd65e] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#174b87]">Tulis menfess pertama <span class="ml-2" aria-hidden="true">→</span></a>
                        </div>
                    @endforelse
                </div>
            </section>

            <aside class="grid content-start gap-5">
                <section class="rounded-[1.5rem] border border-[#dceefa] bg-white p-5 shadow-[0_10px_35px_rgba(11,45,89,.05)] sm:p-6">
                    <div class="flex items-center justify-between gap-3"><div><p class="text-xs font-extrabold tracking-[.14em] text-[#174b87]">PUBLIKASI</p><h2 class="mt-1 font-heading text-lg font-extrabold text-[#0b2d59]">Status terakhir</h2></div><span class="grid size-10 place-items-center rounded-xl bg-[#dceefa] text-[#0b2d59]" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 18V6m0 12h16M8 14l3-3 2 2 5-6" /></svg></span></div>
                    @if ($latestPublication)
                        <p class="mt-4 font-heading text-base font-extrabold text-[#0b2d59]">{{ strtoupper($latestPublication->platform) }} <span class="font-sans font-bold text-[#7890aa]">· {{ str_replace('_', ' ', $latestPublication->status) }}</span></p>
                        <p class="mt-1 text-xs font-semibold text-[#7890aa]">{{ $latestPublication->created_at?->timezone(config('app.timezone', 'Asia/Jakarta'))->format('d M Y · H:i') }} WIB</p>
                        @if ($latestPublication->safe_error)<p class="mt-3 rounded-xl bg-amber-50 p-3 text-sm leading-5 text-amber-900">{{ $latestPublication->safe_error }}</p>@endif
                    @else
                        <p class="mt-4 rounded-xl bg-[#f4f8fb] p-3 text-sm leading-5 text-[#58718e]">Belum ada publikasi. Status kiriman akan muncul di sini.</p>
                    @endif
                </section>

                <section class="rounded-[1.5rem] border border-[#dceefa] bg-white p-5 shadow-[0_10px_35px_rgba(11,45,89,.05)] sm:p-6">
                    <div class="flex items-center justify-between gap-3"><div><p class="text-xs font-extrabold tracking-[.14em] text-[#174b87]">WALLET</p><h2 class="mt-1 font-heading text-lg font-extrabold text-[#0b2d59]">Aktivitas token</h2></div><a href="{{ route('wallet.index') }}" aria-label="Buka wallet" class="grid size-10 place-items-center rounded-xl bg-[#fff2c8] font-extrabold text-[#8b6500] hover:bg-[#ffe89c]">→</a></div>
                    <div class="mt-4 divide-y divide-[#e8f1f8]">
                        @forelse ($recentTransactions as $transaction)
                            <div class="flex items-start justify-between gap-3 py-3 first:pt-0 last:pb-0"><div class="min-w-0"><p class="break-words text-sm font-bold text-[#315276]">{{ $transaction->reason ?? str_replace('_', ' ', $transaction->type) }}</p><p class="mt-1 text-xs font-semibold text-[#7890aa]">{{ $transaction->created_at?->timezone(config('app.timezone', 'Asia/Jakarta'))->format('d M · H:i') }} WIB</p></div><span class="shrink-0 font-heading text-sm font-extrabold {{ $transaction->amount > 0 ? 'text-emerald-700' : 'text-[#315276]' }}">{{ $transaction->amount > 0 ? '+' : '' }}{{ number_format($transaction->amount) }}</span></div>
                        @empty
                            <p class="rounded-xl bg-[#f4f8fb] p-3 text-sm leading-5 text-[#58718e]">Belum ada transaksi token.</p>
                        @endforelse
                    </div>
                    <a href="{{ route('wallet.index') }}" class="mt-4 inline-flex min-h-10 items-center font-extrabold text-sm text-[#174b87] underline decoration-[#ffc629] decoration-2 underline-offset-4">Top up dan lihat riwayat</a>
                </section>
            </aside>
        </div>
    </section>
@endsection
