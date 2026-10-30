<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
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
