@extends('layouts.app')
@section('content')
<section class="mx-auto max-w-6xl px-5 py-10">
    <p class="text-sm font-bold uppercase tracking-[.18em] text-[#a64e73]">SUPER ADMIN</p>
    <div class="mt-2 flex flex-wrap items-end justify-between gap-4">
        <div><h1 class="text-3xl font-bold">Pengguna</h1><p class="mt-2 text-slate-600">Daftar akun, saldo token, dan aktivitas menfess.</p></div>
        <form method="GET" action="{{ route('admin.users.index') }}" class="flex w-full gap-2 sm:w-auto"><label class="sr-only" for="search-user">Cari pengguna</label><input id="search-user" name="search" value="{{ $search }}" placeholder="Nama atau email" class="min-w-0 flex-1 rounded-full border-slate-300 sm:w-72"><button class="rounded-full bg-[#182a4b] px-5 py-2.5 text-sm font-semibold text-white">Cari</button></form>
    </div>
    <div class="mt-7 overflow-hidden rounded-3xl border border-[#ece7e2] bg-white">
        <div class="overflow-x-auto"><table class="w-full min-w-[720px] text-left text-sm">
            <thead class="bg-[#fbf8f4] text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-4">Pengguna</th><th class="px-5 py-4">Role / Status</th><th class="px-5 py-4">Saldo token</th><th class="px-5 py-4">Menfess</th><th class="px-5 py-4"></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($users as $user)
                    <tr class="hover:bg-[#fcfbfa]"><td class="px-5 py-4"><p class="font-semibold">{{ $user->name }}</p><p class="mt-1 text-xs text-slate-500">{{ $user->email }}</p></td><td class="px-5 py-4"><span class="rounded-full bg-[#e7f1fa] px-3 py-1 text-xs font-semibold">{{ str_replace('_', ' ', $user->role) }}</span><p class="mt-2 text-xs text-slate-500">{{ $user->status }}</p></td><td class="px-5 py-4 font-semibold">{{ number_format($user->wallet?->balance ?? 0) }}</td><td class="px-5 py-4">{{ number_format($user->menfesses_count) }}</td><td class="px-5 py-4 text-right"><a class="rounded-full bg-[#182a4b] px-4 py-2 text-xs font-semibold text-white" href="{{ route('admin.users.show', $user) }}">Detail</a></td></tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">Belum ada pengguna yang cocok dengan pencarian.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </div>
    <div class="mt-5">{{ $users->links() }}</div>
</section>
@endsection
