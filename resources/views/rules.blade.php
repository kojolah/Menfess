@extends('layouts.app')

@section('content')
    <section class="mx-auto max-w-4xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
        <a href="{{ auth()->check() ? route('dashboard') : route('home') }}" class="inline-flex min-h-10 items-center gap-2 rounded-lg font-extrabold text-[#174b87] underline decoration-[#ffc629] decoration-2 underline-offset-4 hover:text-[#0b2d59]">← Kembali</a>
        <div class="mt-5 rounded-[2rem] bg-gradient-to-br from-[#0b2d59] to-[#174b87] p-6 text-white shadow-[0_20px_55px_rgba(11,45,89,.14)] sm:p-9">
            <p class="text-xs font-extrabold tracking-[.16em] text-[#ffe08a]">RUANG AMAN UNTUK CERITA KAMPUS</p>
            <h1 class="mt-3 font-heading text-3xl font-extrabold tracking-tight sm:text-4xl">Aturan ruang bersama</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-blue-100 sm:text-base sm:leading-7">Gunakan Unwarfess untuk berbagi cerita dengan aman, saling menghormati, dan menjaga privasi komunitas Warmadewa.</p>
        </div>
        <div class="mt-5 grid gap-3 sm:grid-cols-2">
            <article class="rounded-[1.4rem] border border-[#dceefa] bg-white p-5 shadow-[0_8px_30px_rgba(11,45,89,.04)]"><span class="grid size-10 place-items-center rounded-xl bg-[#dceefa] font-heading font-extrabold text-[#0b2d59]">01</span><h2 class="mt-4 font-heading font-extrabold text-[#0b2d59]">Jaga privasi</h2><p class="mt-2 text-sm leading-6 text-[#58718e]">Jangan membagikan alamat, nomor telepon, identitas, atau informasi pribadi orang lain tanpa izin.</p></article>
            <article class="rounded-[1.4rem] border border-[#dceefa] bg-white p-5 shadow-[0_8px_30px_rgba(11,45,89,.04)]"><span class="grid size-10 place-items-center rounded-xl bg-[#fff2c8] font-heading font-extrabold text-[#8b6500]">02</span><h2 class="mt-4 font-heading font-extrabold text-[#0b2d59]">Hindari ancaman dan pelecehan</h2><p class="mt-2 text-sm leading-6 text-[#58718e]">Ancaman, perundungan, ujaran kebencian, dan doxing dapat ditahan untuk pemeriksaan atau ditolak.</p></article>
            <article class="rounded-[1.4rem] border border-[#dceefa] bg-white p-5 shadow-[0_8px_30px_rgba(11,45,89,.04)]"><span class="grid size-10 place-items-center rounded-xl bg-[#eaf5ed] font-heading font-extrabold text-[#17653a]">03</span><h2 class="mt-4 font-heading font-extrabold text-[#0b2d59]">Pemeriksaan konten</h2><p class="mt-2 text-sm leading-6 text-[#58718e]">Filter otomatis menyensor kata terlarang. Konten yang terdeteksi berisiko dapat diteruskan ke moderator untuk ditinjau.</p></article>
            <article class="rounded-[1.4rem] border border-[#dceefa] bg-white p-5 shadow-[0_8px_30px_rgba(11,45,89,.04)]"><span class="grid size-10 place-items-center rounded-xl bg-[#f1edff] font-heading font-extrabold text-[#44358d]">04</span><h2 class="mt-4 font-heading font-extrabold text-[#0b2d59]">Identitas tetap privat</h2><p class="mt-2 text-sm leading-6 text-[#58718e]">Nama, email, foto profil, dan ID akun tidak dimasukkan ke gambar menfess yang dipublikasikan.</p></article>
            <article class="rounded-[1.4rem] border border-[#dceefa] bg-white p-5 shadow-[0_8px_30px_rgba(11,45,89,.04)] sm:col-span-2"><span class="grid size-10 place-items-center rounded-xl bg-[#fce5ef] font-heading font-extrabold text-[#9b3462]">05</span><h2 class="mt-4 font-heading font-extrabold text-[#0b2d59]">Moderasi dan penghapusan</h2><p class="mt-2 text-sm leading-6 text-[#58718e]">Moderator dapat meminta pemeriksaan lanjutan atau menolak kiriman. Super admin dapat menghapus menfess yang melanggar ketentuan.</p></article>
        </div>
    </section>
@endsection
