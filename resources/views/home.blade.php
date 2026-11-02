@extends('layouts.app')

@section('content')
    <section class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 sm:pt-10 lg:px-8">
        <div class="relative isolate overflow-hidden rounded-[2rem] bg-gradient-to-br from-[#0b2d59] via-[#174b87] to-[#2467a8] px-6 py-9 text-white shadow-[0_24px_70px_rgba(11,45,89,.18)] sm:px-10 sm:py-12 lg:grid lg:grid-cols-[1.05fr_.95fr] lg:items-center lg:gap-8 lg:px-14 lg:py-14">
            <div class="pointer-events-none absolute -right-16 -top-20 -z-10 size-64 rounded-full border-[28px] border-white/5 sm:size-80"></div>
            <div class="pointer-events-none absolute bottom-0 right-1/3 -z-10 size-24 translate-y-1/2 rounded-full bg-[#ffc629]/15 sm:size-36"></div>

            <div class="max-w-2xl">
                <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-extrabold tracking-wide text-[#ffe08a] sm:text-sm">
                    <span class="size-2 rounded-full bg-[#ffc629]"></span> RUANG CERITA MAHASISWA WARMADEWA
                </span>
                <h1 class="mt-5 font-heading text-4xl leading-[1.12] font-extrabold tracking-tight sm:text-5xl lg:text-6xl">Your Voice,<br><span class="text-[#ffc629]">Your Space.</span></h1>
                <p class="mt-5 max-w-xl text-base leading-7 text-blue-50 sm:text-lg sm:leading-8">Selamat datang di Unwarfess. Bagikan cerita, sebarkan informasi, dan terhubung dengan komunitas mahasiswa Warmadewa—dengan identitas tetap anonim di ruang publik.</p>
                <div class="mt-7 flex flex-col gap-3 min-[380px]:flex-row">
                    <a href="{{ auth()->check() ? route('menfesses.create') : route('login') }}" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-2xl bg-[#ffc629] px-5 py-3 text-center font-extrabold text-[#0b2d59] shadow-lg shadow-[#071c38]/15 transition hover:-translate-y-0.5 hover:bg-[#ffda66] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                        {{ auth()->check() ? 'Tulis menfess' : 'Mulai berbagi' }} <span aria-hidden="true">→</span>
                    </a>
                    <a href="{{ route('rules') }}" class="inline-flex min-h-12 items-center justify-center rounded-2xl border border-white/30 bg-white/5 px-5 py-3 text-center font-bold text-white transition hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#ffc629]">Kenali ruang ini</a>
                </div>
                <p class="mt-5 text-sm font-semibold text-blue-100">Nama dan email akun tidak tampil di gambar yang dipublikasikan.</p>
            </div>

            <div class="relative mx-auto mt-10 w-full max-w-md lg:mt-0 lg:justify-self-end">
                <div class="absolute -left-4 top-12 size-20 rounded-[1.75rem] bg-[#dceefa] sm:-left-7 sm:size-24"></div>
                <div class="absolute -right-2 bottom-5 size-24 rounded-full bg-[#ffc629] sm:-right-5 sm:size-28"></div>
                <article class="relative rounded-[1.75rem] border border-white/70 bg-[#fbf8ef] p-5 text-[#102e55] shadow-2xl shadow-[#061a34]/25 sm:p-7">
                    <div class="flex items-center gap-3">
                        <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-white p-1.5 shadow-sm"><img src="{{ asset('logo.png') }}" alt="" class="size-full rounded-xl object-cover"></span>
                        <div class="min-w-0"><p class="font-heading text-sm font-extrabold text-[#0b2d59] sm:text-base">Satu ruang, banyak suara</p><p class="mt-0.5 text-xs font-semibold text-[#58718e]">Dibuat untuk komunitas kampus</p></div>
                    </div>
                    <div class="mt-5 rounded-2xl bg-white p-4 shadow-sm shadow-[#0b2d59]/5 sm:p-5">
                        <div class="flex items-center gap-3">
                            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-[#dceefa] text-[#0b2d59]" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h8m-8 4h5m-9 6 1.8-4.5A8 8 0 1 1 8 19.5L4 20Z" /></svg></span>
                            <span class="min-w-0"><strong class="block text-sm font-extrabold text-[#0b2d59]">Bagikan cerita kampusmu</strong><span class="mt-0.5 block text-xs leading-5 font-semibold text-[#58718e]">Pesanmu diproses dengan menjaga anonimitas.</span></span>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <span class="rounded-full bg-[#fff2c8] px-3 py-1.5 text-xs font-extrabold text-[#775900]">Anonim untuk publik</span>
                            <span class="rounded-full bg-[#0b2d59] px-3 py-1.5 text-xs font-extrabold text-[#ffe08a]">Instagram · X</span>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 sm:py-16 lg:px-8">
        <div class="max-w-2xl">
            <p class="text-xs font-extrabold tracking-[.18em] text-[#174b87]">DIBUAT UNTUK KEBERSAMAAN</p>
            <h2 class="mt-2 font-heading text-2xl font-extrabold tracking-tight text-[#0b2d59] sm:text-3xl">Ruang kampus yang terasa dekat.</h2>
        </div>
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <article class="rounded-[1.5rem] border border-[#dceefa] bg-white p-5 shadow-[0_10px_35px_rgba(11,45,89,.05)] sm:p-6">
                <span class="grid size-12 place-items-center rounded-2xl bg-[#dceefa] text-[#0b2d59]" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s-8-4.4-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 10c0 6.6-8 11-8 11Z" /></svg></span>
                <h3 class="mt-4 font-heading font-bold text-[#0b2d59]">Anonim di ruang publik</h3><p class="mt-2 text-sm leading-6 text-[#58718e]">Identitas akun hanya digunakan untuk autentikasi dan operasional layanan.</p>
            </article>
            <article class="rounded-[1.5rem] border border-[#dceefa] bg-white p-5 shadow-[0_10px_35px_rgba(11,45,89,.05)] sm:p-6">
                <span class="grid size-12 place-items-center rounded-2xl bg-[#fff2c8] text-[#8b6500]" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z" /><path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4" /></svg></span>
                <h3 class="mt-4 font-heading font-bold text-[#0b2d59]">Filter dan keamanan</h3><p class="mt-2 text-sm leading-6 text-[#58718e]">Pemeriksaan otomatis membantu menjaga kiriman sesuai aturan komunitas.</p>
            </article>
            <article class="rounded-[1.5rem] border border-[#dceefa] bg-white p-5 shadow-[0_10px_35px_rgba(11,45,89,.05)] sm:p-6">
                <span class="grid size-12 place-items-center rounded-2xl bg-[#e9e7ff] text-[#44358d]" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="4" width="16" height="16" rx="4" /><path stroke-linecap="round" d="M9 8h6M8 12h8M10 16h4" /></svg></span>
                <h3 class="mt-4 font-heading font-bold text-[#0b2d59]">Satu kiriman, pilihan kanal</h3><p class="mt-2 text-sm leading-6 text-[#58718e]">Pilih Instagram, X, atau keduanya sesuai pilihan yang tersedia.</p>
            </article>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 pb-14 sm:px-6 lg:px-8">
        <div class="flex flex-col items-start justify-between gap-4 rounded-[1.75rem] border border-[#dceefa] bg-[#dceefa]/70 p-6 sm:flex-row sm:items-center sm:p-8">
            <div><h2 class="font-heading text-xl font-extrabold text-[#0b2d59] sm:text-2xl">Punya cerita untuk komunitas?</h2><p class="mt-2 text-sm leading-6 text-[#315276]">Mulai dari satu pesan. Kami bantu prosesnya sampai siap dibagikan.</p></div>
            <a href="{{ auth()->check() ? route('menfesses.create') : route('login') }}" class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-2xl bg-[#ffc629] px-5 py-3 font-extrabold text-[#0b2d59] transition hover:bg-[#ffd65e] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0b2d59]">{{ auth()->check() ? 'Buat menfess' : 'Masuk dengan Google' }} <span class="ml-2" aria-hidden="true">→</span></a>
        </div>
    </section>
@endsection
