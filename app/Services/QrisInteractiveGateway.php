<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class QrisInteractiveGateway
{
    private readonly ?string $baseUrl;

    private readonly ?string $apiKey;

    private readonly ?string $merchantId;

    private readonly string $useTip;

    private readonly int $timeout;

    public function __construct(
        ?string $baseUrl = null,
        ?string $apiKey = null,
        ?string $merchantId = null,
        ?string $useTip = null,
        ?int $timeout = null,
    ) {
        $this->baseUrl = $baseUrl ?? config('qris.base_url', 'https://qris.interactive.co.id/restapi/qris/');
        $this->apiKey = $apiKey ?? config('qris.api_key');
        $this->merchantId = $merchantId ?? config('qris.merchant_id');
        $this->useTip = $useTip ?? (string) config('qris.use_tip', 'no');
        $this->timeout = $timeout ?? (int) config('qris.timeout', 15);
    }

    public static function make(): self
    {
        return new self;
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey) && ! empty($this->merchantId);
    }

    /**
     * Create dynamic QRIS invoice via QRIS InterActive API (show_qris.php).
     *
     * @return array{qris_content: string, qris_request_date: string, qris_invoiceid: string, qris_nmid: string}
     */
    public function createInvoice(string $cliTrxNumber, int $cliTrxAmount): array
    {
        $this->ensureConfigured();

        $endpoint = rtrim($this->baseUrl ?? 'https://qris.interactive.co.id/restapi/qris/', '/').'/show_qris.php';

        try {
            $response = Http::timeout($this->timeout)->get($endpoint, [
                'do' => 'create-invoice',
                'apikey' => $this->apiKey,
                'mID' => $this->merchantId,
                'cliTrxNumber' => $cliTrxNumber,
                'cliTrxAmount' => $cliTrxAmount,
                'useTip' => $this->useTip,
            ]);
        } catch (Throwable $exception) {
            Log::error('QRIS InterActive create invoice connection failed.', [
                'cliTrxNumber' => $cliTrxNumber,
                'exception' => $exception::class,
            ]);

            throw new RuntimeException('Koneksi ke gateway QRIS gagal. Coba beberapa saat lagi.');
        }

        if (! $response->successful()) {
            Log::warning('QRIS InterActive returned HTTP error on create invoice.', [
                'status' => $response->status(),
                'cliTrxNumber' => $cliTrxNumber,
            ]);

            throw new RuntimeException('Gateway QRIS menolak pembuatan invoice (HTTP '.$response->status().').');
        }

        $data = $response->json();

        if (! is_array($data) || ($data['status'] ?? null) !== 'success' || ! isset($data['data']) || ! is_array($data['data'])) {
            $message = is_array($data) && isset($data['data']) && is_string($data['data'])
                ? $data['data']
                : ($data['message'] ?? 'Respons gateway tidak valid');

            Log::warning('QRIS InterActive invoice creation was rejected.', [
                'cliTrxNumber' => $cliTrxNumber,
            ]);

            throw new RuntimeException('Pembuatan invoice QRIS gagal: '.$message);
        }

        $invoiceData = $data['data'];

        if (empty($invoiceData['qris_content']) || empty($invoiceData['qris_invoiceid'])) {
            throw new RuntimeException('Gateway QRIS tidak mengembalikan konten QR yang valid.');
        }

        return [
            'qris_content' => (string) $invoiceData['qris_content'],
            'qris_request_date' => (string) ($invoiceData['qris_request_date'] ?? now()->format('Y-m-d H:i:s')),
            'qris_invoiceid' => (string) $invoiceData['qris_invoiceid'],
            'qris_nmid' => (string) ($invoiceData['qris_nmid'] ?? ''),
        ];
    }

    /**
     * Check invoice payment status via QRIS InterActive API (checkpaid_qris.php).
     *
     * @return array{is_paid: bool, status: string, customer_name: ?string, payment_method: ?string, raw: array<string, mixed>}
     */
    public function checkStatus(string $invid, int $trxvalue, string $trxdate): array
    {
        $this->ensureConfigured();

        $endpoint = rtrim($this->baseUrl ?? 'https://qris.interactive.co.id/restapi/qris/', '/').'/checkpaid_qris.php';

        try {
            $response = Http::timeout($this->timeout)->get($endpoint, [
                'do' => 'checkStatus',
                'apikey' => $this->apiKey,
                'mID' => $this->merchantId,
                'invid' => $invid,
                'trxvalue' => $trxvalue,
                'trxdate' => $trxdate,
            ]);
        } catch (Throwable $exception) {
            Log::error('QRIS InterActive check status connection failed.', [
                'invid' => $invid,
                'exception' => $exception::class,
            ]);

            throw new RuntimeException('Gagal menghubungi gateway QRIS untuk verifikasi status.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('Gateway QRIS mengembalikan HTTP '.$response->status().' saat pengecekan status.');
        }

        $data = $response->json();
        $payload = is_array($data) ? $data : [];
        $status = strtolower((string) ($payload['status'] ?? ''));
        $dataField = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $qrisStatus = strtolower((string) ($dataField['qris_status'] ?? ''));

        $isPaid = $status === 'success' && $qrisStatus === 'paid';

        return [
            'is_paid' => $isPaid,
            'status' => $isPaid ? 'paid' : ($qrisStatus ?: 'unpaid'),
            'customer_name' => isset($dataField['qris_payment_customername']) ? (string) $dataField['qris_payment_customername'] : null,
            'payment_method' => isset($dataField['qris_payment_methodby']) ? (string) $dataField['qris_payment_methodby'] : null,
            'raw' => $payload,
        ];
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('QRIS InterActive belum dikonfigurasi (API Key atau Merchant ID kosong).');
        }
    }
}
