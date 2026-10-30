<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>

    <!-- Primary Meta Tags -->
    <meta name="title" content="Unwarfess — Voice of Warmadewa Students">
    <meta name="description" content="Unwarfess adalah platform menfess otomatis untuk mahasiswa Universitas Warmadewa. Kirim pesan dan menfess secara anonim ke Instagram dan X melalui satu platform.">
    <meta name="keywords" content="Unwarfess, Warmadewa, menfess Warmadewa, menfess Bali, confession mahasiswa, menfess anonim, mahasiswa Universitas Warmadewa">
    <meta name="author" content="Unwarfess">
    <meta name="robots" content="index, follow">
    <meta name="language" content="Indonesian">
    <meta name="theme-color" content="#6366F1">

    <link rel="canonical" href="https://unwarfess.my.id/">

    <!-- Open Graph / Facebook / WhatsApp -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://unwarfess.my.id/">
    <meta property="og:site_name" content="Unwarfess">
    <meta property="og:title" content="Unwarfess — Voice of Warmadewa Students">
    <meta property="og:description" content="Your voice, your space. Kirim menfess secara anonim ke X dan Instagram melalui Unwarfess. Voice of Warmadewa students.">
    <meta property="og:image" content="{{ asset('og.png') }}">
    <meta property="og:image:secure_url" content="{{ asset('og.png') }}">
    <meta property="og:image:alt" content="Unwarfess — Voice of Warmadewa Students">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:locale" content="id_ID">

    <!-- Twitter / X Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="https://unwarfess.my.id/">
    <meta name="twitter:title" content="Unwarfess — Voice of Warmadewa Students">
    <meta name="twitter:description" content="Your voice, your space. Kirim menfess secara anonim ke X dan Instagram melalui Unwarfess.">
    <meta name="twitter:image" content="{{ asset('og.png') }}">
    <meta name="twitter:image:alt" content="Unwarfess — Voice of Warmadewa Students">

    <!-- Mobile Optimization -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Unwarfess">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('logo.png') }}">


    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-[#fbf8f4] text-[#182a4b] antialiased">
    <header class="border-b border-[#e9e3df] bg-white/90">
        <nav class="mx-auto flex max-w-6xl items-center justify-between px-5 py-4">
            <a href="{{ route('home') }}" class="flex items-center gap-3 font-bold tracking-tight">
                <span class="grid size-10 place-items-center rounded-2xl bg-[#f7dce9] text-lg">✳</span>
                <span>{{ config('app.name', 'Ruang Kirim') }}<small class="block text-xs font-medium text-slate-500">suara kampus, tetap anonim</small></span>
            </a>
            <div class="flex items-center gap-3 text-sm font-semibold">
                @auth
                    <a href="{{ route('dashboard') }}" class="hidden sm:block">Dashboard</a>
                    <a href="{{ route('wallet.index') }}" class="hidden sm:block">Wallet</a>
                    @if (auth()->user()->hasRole(\App\UserRole::Moderator, \App\UserRole::Publisher, \App\UserRole::SuperAdmin))<a href="{{ route('admin.dashboard') }}" class="hidden sm:block">Pengelola</a>@endif
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="rounded-full border border-slate-200 px-4 py-2">Keluar</button></form>
                @else
                    <a href="{{ route('login') }}" class="rounded-full bg-[#182a4b] px-5 py-2.5 text-white">Masuk</a>
                @endauth
            </div>
        </nav>
    </header>
    <main>@if (session('notice'))<div class="mx-auto mt-5 max-w-6xl px-5"><div class="rounded-2xl bg-emerald-50 px-4 py-3 text-emerald-800">{{ session('notice') }}</div></div>@endif{{ $slot ?? '' }}@yield('content')</main>
    <footer class="mx-auto mt-16 flex max-w-6xl flex-col gap-2 border-t border-[#e9e3df] px-5 py-8 text-sm text-slate-500 sm:flex-row sm:justify-between"><span>© {{ date('Y') }} {{ config('app.name', 'Ruang Kirim') }} · Cerita kampus, dengan aman.</span><a href="{{ route('rules') }}">Aturan & keamanan</a></footer>
    @livewireScripts
</body>
</html>
