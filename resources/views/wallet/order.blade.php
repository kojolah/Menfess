@extends('layouts.app')
@section('content')
<section class="mx-auto max-w-3xl px-5 py-10">
    <a href="{{ route('wallet.index') }}" class="text-sm font-semibold text-[#a64e73] transition hover:underline">← Kembali ke Wallet</a>

    @if(session('notice'))
        <div class="mt-4 rounded-2xl bg-emerald-50 p-4 text-emerald-800 font-medium">
            {{ session('notice') }}
        </div>
    @endif

    @if(session('cooldown_warning'))
        <div class="mt-4 rounded-2xl bg-amber-50 p-4 text-amber-800 font-medium">
            {{ session('cooldown_warning') }}
        </div>
    @endif

    @if($errors->has('payment'))
        <div class="mt-4 rounded-2xl bg-rose-50 p-4 text-rose-800 font-medium">
            {{ $errors->first('payment') }}
        </div>
    @endif

    <div class="mt-6 overflow-hidden rounded-3xl border border-[#ece7e2] bg-white shadow-sm">
        <div class="border-b border-slate-100 p-6 sm:p-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <span class="text-xs font-bold tracking-wider text-slate-400 uppercase">Pembayaran QRIS InterActive</span>
                    <h1 class="mt-1 text-2xl font-bold text-[#182a4b]">{{ $order->order_id }}</h1>
                </div>
                <div>
                    <span class="inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-bold {{ match($order->status) {
                        'paid' => 'bg-emerald-100 text-emerald-800',
                        'pending' => 'bg-amber-100 text-amber-800',
                        'expired' => 'bg-slate-100 text-slate-600',
                        default => 'bg-rose-100 text-rose-800',
                    } }}">
                        <span class="size-2 rounded-full {{ match($order->status) {
                            'paid' => 'bg-emerald-500',
                            'pending' => 'bg-amber-500 animate-pulse',
                            'expired' => 'bg-slate-400',
                            default => 'bg-rose-500',
                        } }}"></span>
                        {{ match($order->status) {
                            'paid' => 'Pembayaran Berhasil',
                            'pending' => 'Menunggu Pembayaran',
                            'expired' => 'Invoice Kedaluwarsa',
                            'failed' => 'Pembayaran Gagal',
                            default => ucfirst($order->status),
                        } }}
                    </span>
                </div>
            </div>
        </div>

        <div class="p-6 sm:p-8">
            @if($order->isPaid())
                <div class="rounded-2xl bg-emerald-50 p-6 text-center text-emerald-900">
                    <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-emerald-200 text-emerald-800 text-2xl font-bold">
                        ✓
                    </div>
                    <h2 class="mt-4 text-xl font-bold">Pembayaran Telah Dikonfirmasi!</h2>
                    <p class="mt-1 text-sm text-emerald-700">
                        Saldo sebesar <strong>+{{ number_format($order->tokens) }} token</strong> telah ditambahkan ke wallet akun Anda.
                    </p>
                    <div class="mt-6 flex flex-wrap justify-center gap-3">
                        <a href="{{ route('menfesses.create') }}" class="rounded-full bg-[#182a4b] px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-[#253f70]">
                            Kirim Menfess Sekarang
                        </a>
                        <a href="{{ route('wallet.index') }}" class="rounded-full border border-slate-300 bg-white px-6 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                            Lihat Wallet
                        </a>
                    </div>
                </div>
            @elseif($order->isExpired())
                <div class="rounded-2xl bg-slate-50 p-6 text-center text-slate-700">
                    <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-slate-200 text-slate-600 text-2xl font-bold">
                        ✕
                    </div>
                    <h2 class="mt-4 text-xl font-bold">Invoice QRIS Ini Telah Kedaluwarsa</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Sesuai standar QRIS InterActive, invoice berlaku selama 30 menit dari waktu pembuatan. Silakan pilih paket kembali untuk membuat invoice baru.
                    </p>
                    <div class="mt-6">
                        <a href="{{ route('wallet.index') }}" class="rounded-full bg-[#182a4b] px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-[#253f70]">
                            Kembali ke Pilih Paket
                        </a>
                    </div>
                </div>
            @else
                <div class="flex flex-col items-center">
                    <div class="flex items-center gap-2 rounded-full bg-amber-50 px-4 py-1.5 text-xs font-semibold text-amber-800">
                        <span>Sisa waktu pembayaran:</span>
                        <span id="qris-countdown" data-expires-at="{{ $order->expiresAt()?->toIso8601String() }}" class="font-bold">
                            {{ $order->expiresAt()?->diffForHumans(['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) ?? '30 menit' }}
                        </span>
                    </div>

                    <div class="mt-6 rounded-3xl border-4 border-[#182a4b]/10 bg-white p-4 shadow-inner">
                        <img
                            src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($order->qris_content) }}"
                            alt="Scan Here!"
                            width="200"
                            height="200"
                            class="mx-auto block rounded-2xl"
                            id="qris-image"
                        >
                    </div>

                    <div class="mt-4 flex flex-wrap justify-center gap-2">
                        <button type="button" id="download-qris-btn" data-filename="qris-{{ $order->order_id }}" class="rounded-full border border-slate-200 bg-white px-4 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                            ↓ Unduh QRIS
                        </button>
                        <button type="button" id="copy-qris-btn" data-content="{{ $order->qris_content }}" class="rounded-full border border-slate-200 bg-white px-4 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                            Salin Kode QRIS
                        </button>
                    </div>

                    <p class="mt-4 text-center text-xs text-slate-500 max-w-sm">
                        Mendukung semua aplikasi perbankan & e-wallet berstandar QRIS (BCA, Mandiri, BRI, BNI, GoPay, OVO, Dana, ShopeePay, LinkAja, dll).
                    </p>
                </div>

                <div class="mt-8 border-t border-slate-100 pt-6">
                    <form method="POST" action="{{ route('wallet.orders.check-status', $order) }}" class="text-center">
                        @csrf
                        <button type="submit" id="btn-check-status" class="w-full sm:w-auto rounded-full bg-[#182a4b] px-8 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#253f70]">
                            Cek Status Pembayaran
                        </button>
                        <div id="check-cooldown-text" data-seconds="{{ $cooldownSeconds ?? 0 }}" class="mt-2 text-xs text-slate-500 min-h-4">
                            @if(isset($cooldownSeconds) && $cooldownSeconds > 0)
                                Tunggu {{ $cooldownSeconds }}s sebelum cek ulang...
                            @endif
                        </div>
                    </form>
                </div>
            @endif

            <div class="mt-8 border-t border-slate-100 pt-6">
                <h3 class="text-xs font-bold tracking-wider text-slate-400 uppercase">Rincian Transaksi</h3>
                <dl class="mt-3 divide-y divide-slate-100 text-sm">
                    <div class="flex justify-between py-2">
                        <dt class="text-slate-500">Nomor Pesanan</dt>
                        <dd class="font-semibold text-slate-800">{{ $order->order_id }}</dd>
                    </div>
                    @if($order->qris_invoice_id)
                        <div class="flex justify-between py-2">
                            <dt class="text-slate-500">ID Invoice QRIS</dt>
                            <dd class="font-mono text-xs text-slate-700">{{ $order->qris_invoice_id }}</dd>
                        </div>
                    @endif
                    @if($order->qris_nmid)
                        <div class="flex justify-between py-2">
                            <dt class="text-slate-500">NMID Merchant</dt>
                            <dd class="font-mono text-xs text-slate-700">{{ $order->qris_nmid }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between py-2">
                        <dt class="text-slate-500">Nominal Paket</dt>
                        <dd class="font-medium text-slate-800">Rp{{ number_format($order->base_amount_idr ?? $order->amount_idr, 0, ',', '.') }}</dd>
                    </div>
                    @if(($order->unique_code ?? 0) > 0)
                        <div class="flex justify-between py-2 text-emerald-700">
                            <dt class="font-medium">Kode Unik Verifikasi</dt>
                            <dd class="font-bold">+Rp{{ number_format($order->unique_code, 0, ',', '.') }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between py-2">
                        <dt class="text-slate-500">Jumlah Token</dt>
                        <dd class="font-bold text-[#a64e73]">+{{ number_format($order->tokens) }} Token</dd>
                    </div>
                    <div class="flex justify-between py-2 border-t border-slate-200/80 pt-3">
                        <dt class="font-bold text-slate-900">Total Pembayaran</dt>
                        <dd class="text-lg font-extrabold text-[#182a4b]">Rp{{ number_format($order->amount_idr, 0, ',', '.') }}</dd>
                    </div>
                    @if($order->status === 'pending')
                        <div class="py-2">
                            <div class="rounded-xl bg-amber-50 p-3 text-xs text-amber-900 border border-amber-200/60">
                                ⚠️ <strong>PENTING:</strong> Pastikan Anda mentransfer tepat <strong>Rp{{ number_format($order->amount_idr, 0, ',', '.') }}</strong> (termasuk kode unik) agar pembayaran dapat terverifikasi otomatis secara akurat.
                            </div>
                        </div>
                    @endif
                    @if($order->paid_at)
                        <div class="flex justify-between py-2">
                            <dt class="text-slate-500">Waktu Pembayaran</dt>
                            <dd class="font-medium text-slate-700">{{ $order->paid_at->format('d M Y, H:i') }} WIB</dd>
                        </div>
                    @endif
                    @if($order->qris_payment_methodby)
                        <div class="flex justify-between py-2">
                            <dt class="text-slate-500">Metode Bayar</dt>
                            <dd class="font-medium text-slate-700">{{ $order->qris_payment_methodby }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>
    </div>
</section>

<script src="{{ asset('js/qris-renderer.js') }}"></script>
@endsection
