@extends('layouts.app')
@section('content')
<section class="mx-auto max-w-6xl px-5 py-10">
    <a href="{{ route('dashboard') }}" class="text-sm font-semibold text-[#a64e73]">← Dashboard</a>
    <div class="mt-4">
        <p class="text-sm font-semibold text-[#a64e73]">WALLET</p>
        <h1 class="mt-2 text-3xl font-bold">Token untuk publikasi</h1>
    </div>

    @if($errors->has('payment'))
        <div class="mt-5 rounded-2xl bg-rose-50 p-4 text-rose-800">{{ $errors->first('payment') }}</div>
    @endif

    <div class="mt-7 grid gap-5 lg:grid-cols-[.8fr_1.2fr]">
        <div class="rounded-3xl bg-[#e7f3e9] p-7">
            <p class="text-sm text-slate-600">Saldo tersedia</p>
            <p class="mt-2 text-5xl font-bold">{{ number_format($wallet->balance) }} <span class="text-lg">token</span></p>
            <p class="mt-4 text-sm leading-6 text-slate-600">
                Token hanya ditambahkan setelah QRIS InterActive mengonfirmasi pembayaran melalui webhook resmi atau verifikasi status.
                Harga publikasi: Instagram {{ config('menfess.publication_token_costs.instagram') }} token, X {{ config('menfess.publication_token_costs.x') }} token.
                Kuota gratis Instagram (maks 2 kiriman/hari per akun, maks 10 kiriman/hari global) mengurangi biaya Instagram selama kuota masih tersedia.
            </p>
        </div>

        <div>
            <h2 class="text-xl font-bold">Pilih paket top-up</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                @forelse($packages as $package)
                    <article class="rounded-3xl border border-[#ece7e2] bg-white p-5">
                        <div class="flex items-start justify-between">
                            <h3 class="font-bold">{{ $package->name }}</h3>
                            <span class="rounded-full bg-[#f8e5ed] px-3 py-1 text-xs font-bold">{{ $package->tokens }} token</span>
                        </div>
                        <p class="mt-3 text-2xl font-bold">Rp{{ number_format($package->price_idr, 0, ',', '.') }}</p>
                        <p class="mt-1 text-sm text-slate-600">Saldo setelah top-up: {{ number_format($wallet->balance + $package->tokens) }} token.</p>
                        @if(config('qris.api_key') && config('qris.merchant_id'))
                            <form method="POST" action="{{ route('wallet.checkout', $package) }}" class="mt-4">
                                @csrf
                                <button class="w-full rounded-full bg-[#182a4b] px-4 py-3 font-semibold text-white transition hover:bg-[#253f70]">
                                    Bayar dengan QRIS
                                </button>
                            </form>
                        @else
                            <button disabled class="mt-4 w-full cursor-not-allowed rounded-full bg-slate-100 px-4 py-3 text-sm font-semibold text-slate-500">
                                QRIS belum dikonfigurasi
                            </button>
                        @endif
                    </article>
                @empty
                    <div class="rounded-3xl border border-dashed border-slate-300 p-7 text-sm text-slate-500">Paket top-up belum tersedia.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="mt-10 grid gap-8 lg:grid-cols-2">
        <div>
            <h2 class="text-xl font-bold">Riwayat transaksi token</h2>
            <div class="mt-4 overflow-hidden rounded-3xl border border-[#ece7e2] bg-white">
                @forelse($transactions as $transaction)
                    <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 last:border-0">
                        <div>
                            <p class="font-semibold">{{ ucfirst(str_replace('_', ' ', $transaction->type)) }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $transaction->reason }} · {{ $transaction->created_at->format('d M Y, H:i') }}</p>
                        </div>
                        <span class="font-bold {{ $transaction->amount > 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                            {{ $transaction->amount > 0 ? '+' : '' }}{{ number_format($transaction->amount) }}
                        </span>
                    </div>
                @empty
                    <div class="p-7 text-center text-sm text-slate-500">Belum ada mutasi token.</div>
                @endforelse
            </div>
            <div class="mt-4">{{ $transactions->links() }}</div>
        </div>

        <div>
            <h2 class="text-xl font-bold">Status pembayaran QRIS</h2>
            <div class="mt-4 space-y-3">
                @forelse($orders as $order)
                    <a href="{{ route('wallet.orders.show', $order) }}" class="flex items-center justify-between gap-3 rounded-2xl border border-[#ece7e2] bg-white px-5 py-4 transition hover:border-[#182a4b]/30">
                        <div>
                            <p class="font-semibold">{{ $order->order_id }}</p>
                            <p class="mt-1 text-sm text-slate-500">Rp{{ number_format($order->amount_idr, 0, ',', '.') }} · {{ $order->tokens }} token</p>
                            @if($order->status === 'pending' && $order->qris_request_date)
                                <p class="mt-0.5 text-xs text-amber-600">Berlaku s/d {{ $order->qris_request_date->addMinutes(30)->format('H:i') }} WIB</p>
                            @endif
                        </div>
                        <div class="text-right">
                            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ match($order->status) {
                                'paid' => 'bg-emerald-100 text-emerald-800',
                                'pending' => 'bg-amber-100 text-amber-800',
                                'expired' => 'bg-slate-100 text-slate-600',
                                default => 'bg-rose-100 text-rose-800',
                            } }}">
                                {{ match($order->status) {
                                    'paid' => 'Lunas',
                                    'pending' => 'Menunggu Pembayaran',
                                    'expired' => 'Kedaluwarsa',
                                    'failed' => 'Gagal',
                                    default => ucfirst($order->status),
                                } }}
                            </span>
                            <span class="block text-xs text-[#a64e73] mt-1 font-semibold">Lihat detail →</span>
                        </div>
                    </a>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 p-6 text-sm text-slate-500">Belum ada order pembayaran.</div>
                @endforelse
            </div>
        </div>
    </div>
</section>
@endsection
