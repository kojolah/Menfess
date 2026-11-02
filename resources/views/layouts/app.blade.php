<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name', 'Unwarfess') }}</title>

    <meta name="title" content="Unwarfess — Voice of Warmadewa Students">
    <meta name="description" content="Unwarfess adalah platform menfess otomatis untuk mahasiswa Universitas Warmadewa. Kirim pesan anonim ke Instagram dan X.">
    <meta name="keywords" content="Unwarfess, Warmadewa, menfess Warmadewa, confession mahasiswa">
    <meta name="author" content="Unwarfess">
    <meta name="robots" content="index, follow">
    <meta name="theme-color" content="#0b2d59">

    <link rel="canonical" href="{{ config('app.url') }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ config('app.url') }}">
    <meta property="og:site_name" content="Unwarfess">
    <meta property="og:title" content="Unwarfess — Voice of Warmadewa Students">
    <meta property="og:description" content="Your voice, your space. Cerita kampus bersama komunitas Warmadewa.">
    <meta property="og:image" content="{{ asset('og.png') }}">
    <meta property="og:image:secure_url" content="{{ asset('og.png') }}">
    <meta property="og:image:alt" content="Unwarfess — Voice of Warmadewa Students">
    <meta property="og:locale" content="id_ID">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Unwarfess — Voice of Warmadewa Students">
    <meta name="twitter:description" content="Your voice, your space. Cerita kampus bersama komunitas Warmadewa.">
    <meta name="twitter:image" content="{{ asset('og.png') }}">

    <link rel="icon" type="image/png" href="{{ asset('logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;500;600;700;800&family=Poppins:wght@600;700;800;900&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-[#fbf8ef] font-sans text-[#102e55] antialiased">
    <header class="relative z-30 border-b border-[#dceefa] bg-white/95 shadow-[0_4px_20px_rgba(11,45,89,.04)] backdrop-blur">
        <nav x-data="{ open: false }" @keydown.escape.window="if (open) { open = false; $refs.menuButton.focus(); }" @click.outside="open = false" aria-label="Navigasi utama" class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex min-h-[4.5rem] items-center justify-between gap-3 py-2">
                <a href="{{ auth()->check() ? route('dashboard') : route('home') }}" class="flex min-w-0 items-center gap-3 rounded-xl focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#ffc629]" aria-label="Unwarfess, beranda">
                    <img src="{{ asset('logo.png') }}" alt="Logo Unwarfess" width="44" height="44" class="size-11 shrink-0 rounded-2xl object-cover">
                    <span class="min-w-0 leading-tight">
                        <span class="block truncate font-heading text-base font-extrabold tracking-tight text-[#0b2d59] sm:text-lg">Unwarfess</span>
                        <span class="hidden text-xs font-semibold text-[#58718e] sm:block">Voice of Warmadewa Students</span>
                    </span>
                </a>

                <div class="hidden items-center gap-1 xl:flex">
                    @auth
                        <x-navigation-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Dashboard</x-navigation-link>
                        <x-navigation-link :href="route('menfesses.create')" :active="request()->routeIs('menfesses.create')">Buat menfess</x-navigation-link>
                        <x-navigation-link :href="route('menfesses.index')" :active="request()->routeIs('menfesses.index', 'menfesses.show')">Riwayat</x-navigation-link>
                        <x-navigation-link :href="route('wallet.index')" :active="request()->routeIs('wallet.*')">Wallet</x-navigation-link>
                        @if (auth()->user()->hasRole(\App\UserRole::Moderator, \App\UserRole::Publisher, \App\UserRole::SuperAdmin))
                            <x-navigation-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">Pengelola</x-navigation-link>
                        @endif
                        @if (auth()->user()->hasRole(\App\UserRole::Moderator, \App\UserRole::SuperAdmin))
                            <x-navigation-link :href="route('admin.moderation.index')" :active="request()->routeIs('admin.moderation.*')">Moderasi</x-navigation-link>
                        @endif
                        @if (auth()->user()->hasRole(\App\UserRole::Publisher, \App\UserRole::SuperAdmin))
                            <x-navigation-link :href="route('admin.publications.index')" :active="request()->routeIs('admin.publications.*')">Publikasi</x-navigation-link>
                        @endif
                        @if (auth()->user()->hasRole(\App\UserRole::SuperAdmin))
                            <x-navigation-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">Pengguna</x-navigation-link>
                            <x-navigation-link :href="route('admin.content-filters.index')" :active="request()->routeIs('admin.content-filters.*')">Filter</x-navigation-link>
                        @endif
                        <x-navigation-link :href="route('rules')" :active="request()->routeIs('rules')">Aturan</x-navigation-link>
                        <form method="POST" action="{{ route('logout') }}" class="ml-1">
                            @csrf
                            <button type="submit" class="min-h-11 rounded-xl border border-[#c8d9e9] px-4 py-2 font-bold text-[#0b2d59] transition hover:border-[#0b2d59] hover:bg-[#dceefa] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#ffc629]">Keluar</button>
                        </form>
                    @else
                        <x-navigation-link :href="route('home')" :active="request()->routeIs('home')">Beranda</x-navigation-link>
                        <x-navigation-link :href="route('rules')" :active="request()->routeIs('rules')">Aturan</x-navigation-link>
                        <a href="{{ route('login') }}" class="ml-2 inline-flex min-h-11 items-center justify-center rounded-xl bg-[#ffc629] px-5 py-2 font-extrabold text-[#0b2d59] shadow-sm transition hover:bg-[#ffd65e] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0b2d59]">Masuk</a>
                    @endauth
                </div>

                <button x-ref="menuButton" type="button" class="inline-flex size-12 shrink-0 items-center justify-center rounded-2xl border border-[#c8d9e9] bg-white text-[#0b2d59] shadow-sm transition hover:bg-[#dceefa] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#ffc629] xl:hidden" aria-label="Buka menu navigasi" aria-controls="mobile-navigation" :aria-expanded="open.toString()" @click="open = !open">
                    <svg x-show="!open" xmlns="http://www.w3.org/2000/svg" class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                    <svg x-cloak x-show="open" xmlns="http://www.w3.org/2000/svg" class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                </button>
            </div>

            <div id="mobile-navigation" x-cloak x-show="open" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="-translate-y-2 opacity-0" x-transition:enter-end="translate-y-0 opacity-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="-translate-y-2 opacity-0" class="border-t border-[#dceefa] py-3 xl:hidden">
                <div class="grid gap-1">
                    @auth
                        <x-navigation-link mobile :href="route('dashboard')" :active="request()->routeIs('dashboard')">Dashboard</x-navigation-link>
                        <x-navigation-link mobile :href="route('menfesses.create')" :active="request()->routeIs('menfesses.create')">Buat menfess</x-navigation-link>
                        <x-navigation-link mobile :href="route('menfesses.index')" :active="request()->routeIs('menfesses.index', 'menfesses.show')">Riwayat menfess</x-navigation-link>
                        <x-navigation-link mobile :href="route('wallet.index')" :active="request()->routeIs('wallet.*')">Wallet & token</x-navigation-link>
                        @if (auth()->user()->hasRole(\App\UserRole::Moderator, \App\UserRole::Publisher, \App\UserRole::SuperAdmin))
                            <x-navigation-link mobile :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">Dashboard pengelola</x-navigation-link>
                        @endif
                        @if (auth()->user()->hasRole(\App\UserRole::Moderator, \App\UserRole::SuperAdmin))
                            <x-navigation-link mobile :href="route('admin.moderation.index')" :active="request()->routeIs('admin.moderation.*')">Antrean moderasi</x-navigation-link>
                        @endif
                        @if (auth()->user()->hasRole(\App\UserRole::Publisher, \App\UserRole::SuperAdmin))
                            <x-navigation-link mobile :href="route('admin.publications.index')" :active="request()->routeIs('admin.publications.*')">Publication center</x-navigation-link>
                        @endif
                        @if (auth()->user()->hasRole(\App\UserRole::SuperAdmin))
                            <x-navigation-link mobile :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">Pengelolaan pengguna</x-navigation-link>
                            <x-navigation-link mobile :href="route('admin.content-filters.index')" :active="request()->routeIs('admin.content-filters.*')">Filter konten</x-navigation-link>
                        @endif
                        <x-navigation-link mobile :href="route('rules')" :active="request()->routeIs('rules')">Aturan & keamanan</x-navigation-link>
                        <form method="POST" action="{{ route('logout') }}" class="px-1 pt-2">
                            @csrf
                            <button type="submit" class="flex min-h-12 w-full items-center rounded-xl border border-[#c8d9e9] px-4 py-3 text-left font-extrabold text-[#0b2d59] transition hover:bg-[#dceefa] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#ffc629]">Keluar dari akun</button>
                        </form>
                    @else
                        <x-navigation-link mobile :href="route('home')" :active="request()->routeIs('home')">Beranda</x-navigation-link>
                        <x-navigation-link mobile :href="route('rules')" :active="request()->routeIs('rules')">Aturan & keamanan</x-navigation-link>
                        <a href="{{ route('login') }}" @click="open = false" class="flex min-h-12 items-center justify-center rounded-xl bg-[#ffc629] px-4 py-3 font-extrabold text-[#0b2d59] transition hover:bg-[#ffd65e] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0b2d59]">Masuk dengan Google</a>
                    @endauth
                </div>
            </div>
        </nav>
    </header>

    <main>
        @if (session('notice'))
            <div class="mx-auto mt-5 max-w-7xl px-4 sm:px-6 lg:px-8"><div role="status" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 font-semibold text-emerald-900">{{ session('notice') }}</div></div>
        @endif
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    <footer class="mt-16 border-t border-[#dceefa] bg-white">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-7 text-sm font-semibold text-[#58718e] sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <span>© {{ now(config('app.timezone', 'Asia/Jakarta'))->year }} Unwarfess · Voice of Warmadewa Students</span>
            <a href="{{ route('rules') }}" class="w-fit rounded-lg underline decoration-[#ffc629] decoration-2 underline-offset-4 hover:text-[#0b2d59]">Aturan & keamanan</a>
        </div>
    </footer>
    @livewireScripts
</body>
</html>
