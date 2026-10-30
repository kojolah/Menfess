<?php

namespace App\Http\Controllers;

use App\Models\PaymentOrder;
use App\Models\TopupPackage;
use App\Models\Wallet;
use App\Services\QrisInteractiveGateway;
use App\Services\UniquePaymentAmountService;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class WalletController extends Controller
{
    public function index(): View
    {
        $wallet = Wallet::firstOrCreate(['user_id' => auth()->id()]);

        return view('wallet.index', [
            'wallet' => $wallet,
            'packages' => TopupPackage::where('is_active', true)->orderBy('price_idr')->get(),
            'transactions' => $wallet->transactions()->latest()->paginate(15),
            'orders' => PaymentOrder::where('user_id', auth()->id())->latest()->take(10)->get(),
        ]);
    }

    public function checkout(
        TopupPackage $package,
        QrisInteractiveGateway $gateway,
        UniquePaymentAmountService $uniqueAmountService,
    ): RedirectResponse {
        abort_unless($package->is_active, 404);
        abort_if(! $gateway->isConfigured(), 503, 'Pembayaran QRIS belum dikonfigurasi oleh administrator.');

        $orderId = 'QR-'.Str::upper((string) Str::ulid());
        $userId = (int) auth()->id();
        try {
            $order = DB::transaction(function () use ($package, $orderId, $userId, $uniqueAmountService): PaymentOrder {
                $allocation = $uniqueAmountService->allocate($package->price_idr, $orderId);
                $order = PaymentOrder::create([
                    'order_id' => $orderId,
                    'user_id' => $userId,
                    'topup_package_id' => $package->id,
                    'base_amount_idr' => $allocation['base_amount'],
                    'unique_code' => $allocation['unique_code'],
                    'amount_idr' => $allocation['final_amount'],
                    'tokens' => $package->tokens,
                    'status' => 'pending',
                    'payment_provider' => 'qris',
                ]);

                return $order;
            }, attempts: 3);
        } catch (Throwable $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()]);
        }

        try {
            $invoice = $gateway->createInvoice($order->order_id, $order->amount_idr);

            $order->update([
                'qris_invoice_id' => $invoice['qris_invoiceid'],
                'qris_content' => $invoice['qris_content'],
                'qris_nmid' => $invoice['qris_nmid'],
                'qris_request_date' => Carbon::parse($invoice['qris_request_date'], 'Asia/Jakarta'),
                'provider_payload' => $invoice,
            ]);

            return redirect()->route('wallet.orders.show', $order);
        } catch (Throwable $exception) {
            $order->update(['status' => 'failed']);
            $uniqueAmountService->releaseForOrder($order->order_id);

            Log::warning('QRIS InterActive checkout creation failed.', [
                'order_id' => $order->order_id,
                'exception' => $exception::class,
            ]);

            return back()->withErrors(['payment' => 'Invoice QRIS tidak dapat dibuat: '.$exception->getMessage()]);
        }
    }

    public function show(PaymentOrder $order, UniquePaymentAmountService $uniqueAmountService): View
    {
        abort_unless($order->user_id === auth()->id(), 403);

        if ($order->isExpired() && $order->status === 'pending') {
            $order->update(['status' => 'expired']);
            $uniqueAmountService->releaseForOrder($order->order_id);
        }

        $cooldown = (int) config('qris.check_status_cooldown_seconds', 10);
        $secondsSinceLastCheck = $order->last_checked_at ? now()->diffInSeconds($order->last_checked_at) : 999;
        $canCheck = $secondsSinceLastCheck >= $cooldown;

        return view('wallet.order', [
            'order' => $order,
            'canCheck' => $canCheck,
            'cooldownSeconds' => max(0, $cooldown - $secondsSinceLastCheck),
        ]);
    }

    public function checkStatus(
        PaymentOrder $order,
        QrisInteractiveGateway $gateway,
        WalletService $walletService,
        UniquePaymentAmountService $uniqueAmountService,
    ): RedirectResponse {
        abort_unless($order->user_id === auth()->id(), 403);

        if ($order->isPaid()) {
            return back()->with('notice', 'Pembayaran sudah terverifikasi dan token telah ditambahkan ke wallet Anda.');
        }

        if ($order->isExpired()) {
            $order->update(['status' => 'expired']);
            $uniqueAmountService->releaseForOrder($order->order_id);

            return back()->withErrors(['payment' => 'Invoice QRIS sudah kedaluwarsa (masa berlaku 30 menit). Silakan pilih paket untuk membuat invoice baru.']);
        }

        if ($order->status !== 'pending') {
            return back()->withErrors(['payment' => 'Status invoice ini tidak dapat diperiksa kembali.']);
        }

        $cooldown = (int) config('qris.check_status_cooldown_seconds', 10);
        $checkStarted = DB::transaction(function () use ($order, $cooldown): bool {
            $locked = PaymentOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending' || $locked->isExpired()) {
                return false;
            }

            if ($locked->last_checked_at && now()->diffInSeconds($locked->last_checked_at) < $cooldown) {
                return false;
            }

            $locked->update(['last_checked_at' => now()]);

            return true;
        }, attempts: 3);

        if (! $checkStarted) {
            $order->refresh();
            if ($order->isExpired()) {
                $order->update(['status' => 'expired']);
                $uniqueAmountService->releaseForOrder($order->order_id);

                return back()->withErrors(['payment' => 'Invoice QRIS sudah kedaluwarsa (masa berlaku 30 menit). Silakan pilih paket untuk membuat invoice baru.']);
            }

            $remaining = $order->last_checked_at
                ? max(1, $cooldown - (int) now()->diffInSeconds($order->last_checked_at))
                : $cooldown;

            return back()->with('cooldown_warning', "Harap tunggu {$remaining} detik sebelum memeriksa status kembali.");
        }

        try {
            $trxDates = [
                $order->qris_request_date?->copy()->timezone('Asia/Jakarta')->format('Y-m-d'),
                now('Asia/Jakarta')->format('Y-m-d'),
            ];
            $result = ['is_paid' => false, 'payment_method' => null, 'customer_name' => null, 'raw' => []];

            foreach (array_unique(array_filter($trxDates)) as $trxDate) {
                $result = $gateway->checkStatus(
                    invid: (string) $order->qris_invoice_id,
                    trxvalue: (int) $order->amount_idr,
                    trxdate: $trxDate,
                );

                if ($result['is_paid']) {
                    break;
                }
            }

            if ($result['is_paid']) {
                DB::transaction(function () use ($order, $result, $walletService, $uniqueAmountService): void {
                    $locked = PaymentOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
                    if ($locked->status !== 'pending' || $locked->isExpired()) {
                        if ($locked->status === 'pending') {
                            $locked->update(['status' => 'expired']);
                            $uniqueAmountService->releaseForOrder($locked->order_id);
                        }

                        return;
                    }

                    $locked->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                        'qris_paid_date' => now(),
                        'qris_payment_methodby' => $result['payment_method'],
                        'qris_customer_name' => $result['customer_name'],
                        'provider_payload' => $result['raw'],
                    ]);

                    $uniqueAmountService->releaseForOrder($locked->order_id);

                    $wallet = Wallet::firstOrCreate(['user_id' => $locked->user_id]);
                    $walletService->adjust(
                        $wallet->id,
                        $locked->tokens,
                        'topup',
                        'payment:'.$locked->order_id,
                        PaymentOrder::class,
                        $locked->id,
                        'Top-up terverifikasi QRIS InterActive',
                    );
                }, attempts: 3);

                return redirect()->route('wallet.orders.show', $order)->with('notice', 'Pembayaran berhasil dikonfirmasi! Saldo token telah ditambahkan.');
            }

            return back()->with('notice', 'Pembayaran belum terdeteksi. Silakan selesaikan transfer QRIS di aplikasi Anda lalu klik periksa kembali.');
        } catch (Throwable $exception) {
            return back()->withErrors(['payment' => 'Gagal memeriksa status: '.$exception->getMessage()]);
        }
    }
}
