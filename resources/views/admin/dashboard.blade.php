@extends('layouts.app')

@section('content')
    <section class="mx-auto max-w-7xl px-4 py-7 sm:px-6 sm:py-10 lg:px-8">
        <div class="relative isolate overflow-hidden rounded-[2rem] bg-gradient-to-br from-[#0b2d59] to-[#174b87] p-6 text-white shadow-[0_20px_60px_rgba(11,45,89,.16)] sm:p-9">
            <div class="pointer-events-none absolute -right-10 -top-16 -z-10 size-56 rounded-full border-[22px] border-white/5 sm:size-72"></div>
            <p class="inline-flex rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-extrabold tracking-[.16em] text-[#ffe08a]">RUANG PENGELOLA</p>
            <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div><h1 class="font-heading text-3xl font-extrabold tracking-tight sm:text-4xl">Ringkasan operasional</h1><p class="mt-2 max-w-2xl text-sm leading-6 text-blue-100 sm:text-base">Pantau moderasi, publikasi, dan pembayaran terverifikasi dari satu ruang kerja.</p></div>
                <span class="inline-flex w-fit items-center gap-2 rounded-xl bg-[#ffc629] px-4 py-2.5 text-sm font-extrabold text-[#0b2d59]"><span class="size-2 rounded-full {{ config('menfess.publisher_mode') === 'live' ? 'bg-emerald-700' : 'bg-[#8b6500]' }}"></span>{{ config('menfess.publisher_mode') === 'live' ? 'Mode API live' : 'Mode simulasi' }}</span>
            </div>
        </div>
        @if (config('menfess.publisher_mode') !== 'live')
            <div role="status" class="mt-5 rounded-2xl border border-[#f0d98b] bg-[#fff9e8] p-4 text-sm leading-6 text-[#6e5515]">Simulasi aktif. Job tidak membuat post di Instagram atau X; hasil publikasi harus ditinjau.</div>
        @endif

        <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ([['Pengguna', $metrics['users'], 'akun terdaftar'], ['Menfess', $metrics['menfesses'], 'semua status'], ['Moderasi', $metrics['moderation'], 'menunggu tinjauan'], ['Publikasi', $metrics['published'], 'berhasil dikonfirmasi'], ['Perlu perhatian', $metrics['failed'], 'gagal atau perlu tinjauan'], ['Pembayaran', $metrics['payments'], 'top-up terverifikasi'], ['Token dipakai', number_format($metrics['tokens_used']), 'token tercatat keluar'], ['Top-up terverifikasi', 'Rp'.number_format($metrics['verified_topups_idr'], 0, ',', '.'), 'nilai transaksi, bukan laba']] as [$label, $value, $hint])
                <article class="min-w-0 rounded-[1.35rem] border border-[#dceefa] bg-white p-4 shadow-[0_8px_30px_rgba(11,45,89,.04)] sm:p-5">
                    <p class="text-xs font-extrabold leading-5 text-[#58718e] sm:text-sm">{{ $label }}</p>
                    <p class="mt-3 break-words font-heading text-xl font-extrabold text-[#0b2d59] sm:text-2xl">{{ $value }}</p>
                    <p class="mt-1 text-[11px] leading-4 font-semibold text-[#7890aa] sm:text-xs">{{ $hint }}</p>
                </article>
            @endforeach
        </div>

        <div class="mt-6 grid gap-5 lg:grid-cols-[.8fr_1.2fr]">
            <article class="rounded-[1.5rem] border border-[#dceefa] bg-white p-5 shadow-[0_10px_35px_rgba(11,45,89,.05)] sm:p-6">
                <p class="text-xs font-extrabold tracking-[.15em] text-[#174b87]">AKSES SESUAI PERAN</p><h2 class="mt-1 font-heading text-xl font-extrabold text-[#0b2d59]">Ruang kerja</h2>
                <div class="mt-4 grid gap-2.5">
                    @if (auth()->user()->hasRole(\App\UserRole::Moderator, \App\UserRole::SuperAdmin))
                        <a class="flex min-h-12 items-center justify-between rounded-xl bg-[#dceefa] px-4 py-3 font-extrabold text-[#0b2d59] transition hover:bg-[#c9e4f5]" href="{{ route('admin.moderation.index') }}">Antrean moderasi <span aria-hidden="true">→</span></a>
                    @endif
                    @if (auth()->user()->hasRole(\App\UserRole::Publisher, \App\UserRole::SuperAdmin))
                        <a class="flex min-h-12 items-center justify-between rounded-xl bg-[#fff2c8] px-4 py-3 font-extrabold text-[#0b2d59] transition hover:bg-[#ffe89c]" href="{{ route('admin.publications.index') }}">Publication center <span aria-hidden="true">→</span></a>
                    @endif
                    @if (auth()->user()->hasRole(\App\UserRole::SuperAdmin))
                        <a class="flex min-h-12 items-center justify-between rounded-xl bg-[#eaf5ed] px-4 py-3 font-extrabold text-[#0b2d59] transition hover:bg-[#d9eddf]" href="{{ route('admin.content-filters.index') }}">Filter konten otomatis <span aria-hidden="true">→</span></a>
                        <a class="flex min-h-12 items-center justify-between rounded-xl bg-[#f1edff] px-4 py-3 font-extrabold text-[#0b2d59] transition hover:bg-[#e5defe]" href="{{ route('admin.users.index') }}">Pengguna dan riwayat <span aria-hidden="true">→</span></a>
                    @endif
                </div>
                <p class="mt-4 rounded-xl bg-[#f4f8fb] p-3 text-xs leading-5 font-semibold text-[#58718e]">Top-up terverifikasi menunjukkan nilai pembayaran bruto; angka ini bukan perhitungan laba bersih.</p>
            </article>

            <article class="min-w-0 rounded-[1.5rem] border border-[#dceefa] bg-white p-5 shadow-[0_10px_35px_rgba(11,45,89,.05)] sm:p-6">
                <div class="flex flex-wrap items-end justify-between gap-2"><div><p class="text-xs font-extrabold tracking-[.15em] text-[#174b87]">KANAL SOSIAL</p><h2 class="mt-1 font-heading text-xl font-extrabold text-[#0b2d59]">Aktivitas publikasi terbaru</h2></div><span class="text-xs font-bold text-[#7890aa]">Maks. 6 job</span></div>
                <div class="mt-4 divide-y divide-[#e8f1f8]">
                    @forelse ($recentPublications as $job)
                        <div class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                            <div class="min-w-0"><p class="break-words font-bold text-[#0b2d59]">{{ $job->menfess->public_number }} <span class="text-[#8ba0b7]">·</span> {{ strtoupper($job->platform) }}</p><p class="mt-1 text-xs font-semibold text-[#7890aa]">{{ $job->created_at?->timezone(config('app.timezone', 'Asia/Jakarta'))->format('d M Y · H:i') }} WIB</p></div>
                            @php($statusTone = $job->status === 'published' ? 'bg-emerald-50 text-emerald-800 ring-emerald-200' : (in_array($job->status, ['failed', 'needs_review'], true) ? 'bg-rose-50 text-rose-800 ring-rose-200' : 'bg-amber-50 text-amber-800 ring-amber-200'))
                            <span class="rounded-full px-3 py-1 text-xs font-extrabold capitalize ring-1 ring-inset {{ $statusTone }}">{{ str_replace('_', ' ', $job->status) }}</span>
                        </div>
                    @empty
                        <p class="rounded-xl bg-[#f4f8fb] p-4 text-sm text-[#58718e]">Belum ada aktivitas publikasi.</p>
                    @endforelse
                </div>
            </article>
        </div>
    </section>
@endsection
