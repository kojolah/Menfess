<?php

namespace Tests\Feature;

use App\Models\PaymentOrder;
use App\Models\TopupPackage;
use App\Models\User;
use App\Models\Wallet;
use App\Services\UniquePaymentAmountService;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class WalletAndQrisPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_rejects_a_debit_that_would_make_balance_negative(): void
    {
        $wallet = Wallet::create(['user_id' => User::factory()->create()->id, 'balance' => 3]);

        $this->expectException(RuntimeException::class);
        app(WalletService::class)->adjust($wallet->id, -4, 'publication', 'job:one');
    }

    public function test_wallet_applies_an_idempotent_credit_only_once(): void
    {
        $wallet = Wallet::create(['user_id' => User::factory()->create()->id, 'balance' => 0]);

        $first = app(WalletService::class)->adjust($wallet->id, 25, 'topup', 'payment:one');
        $second = app(WalletService::class)->adjust($wallet->id, 25, 'topup', 'payment:one');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(25, (int) $wallet->fresh()->balance);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_qris_code_is_displayed_using_img_element_with_url_encoded_payload(): void
    {
        $user = User::factory()->create();
        $rawQrisPayload = '00020101021226510014ID.LINKAJA.01011893600002011000000000005204541153033605405100005802ID5911UNWARFESS6007JAKARTA61051234062070703A016304';
        $order = PaymentOrder::create([
            'order_id' => 'QR-IMG-TEST-01',
            'user_id' => $user->id,
            'base_amount_idr' => 10000,
            'unique_code' => 123,
            'amount_idr' => 10123,
            'tokens' => 10,
            'status' => 'pending',
            'payment_provider' => 'qris',
            'qris_invoice_id' => 'INV-IMG-01',
            'qris_content' => $rawQrisPayload,
            'qris_request_date' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('wallet.orders.show', $order));

        $expectedUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data='.urlencode($rawQrisPayload);

        $response->assertOk()
            ->assertSee('<img', false)
            ->assertSee('alt="Scan Here!"', false)
            ->assertSee('width="200"', false)
            ->assertSee('height="200"', false)
            ->assertSee($expectedUrl, false)
            ->assertDontSee('<canvas id="qris-canvas"', false);
    }

    public function test_user_can_create_qris_checkout_invoice_with_consistent_unique_amount(): void
    {
        config([
            'qris.base_url' => 'https://qris.interactive.co.id/restapi/qris/',
            'qris.api_key' => 'test-api-key',
            'qris.merchant_id' => 'MID-TEST-99',
            'qris.use_tip' => 'no',
            'qris.min_unique_code' => 100,
            'qris.max_unique_code' => 999,
        ]);

        Http::fake([
            'https://qris.interactive.co.id/restapi/qris/show_qris.php*' => Http::response([
                'status' => 'success',
                'data' => [
                    'qris_content' => '00020101021226510014ID.LINKAJA.01011893600002011000000000005204541153033605405100005802ID5911UNWARFESS6007JAKARTA61051234062070703A016304',
                    'qris_request_date' => '2026-10-29 20:00:00',
                    'qris_invoiceid' => 'INV-TEST-QRIS-001',
                    'qris_nmid' => 'ID102000000001',
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $package = TopupPackage::create([
            'name' => '10 Token',
            'tokens' => 10,
            'price_idr' => 10000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('wallet.checkout', $package));

        $order = PaymentOrder::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('qris', $order->payment_provider);
        $this->assertSame('pending', $order->status);
        $this->assertSame('INV-TEST-QRIS-001', $order->qris_invoice_id);
        $this->assertSame(10000, (int) $order->base_amount_idr);
        $this->assertGreaterThanOrEqual(100, (int) $order->unique_code);
        $this->assertLessThanOrEqual(999, (int) $order->unique_code);
        $this->assertSame((int) $order->amount_idr, (int) ($order->base_amount_idr + $order->unique_code));

        // Verify that the exact amount sent to the QRIS provider matches final amount
        Http::assertSent(function (ClientRequest $request) use ($order) {
            return str_contains($request->url(), 'show_qris.php')
                && (int) $request['cliTrxAmount'] === (int) $order->amount_idr;
        });

        $response->assertRedirect(route('wallet.orders.show', $order));
    }

    public function test_two_pending_orders_do_not_receive_the_same_unique_amount(): void
    {
        config([
            'qris.base_url' => 'https://qris.interactive.co.id/restapi/qris/',
            'qris.api_key' => 'test-api-key',
            'qris.merchant_id' => 'MID-TEST-99',
            'qris.min_unique_code' => 1,
            'qris.max_unique_code' => 999,
        ]);

        Http::fake([
            'https://qris.interactive.co.id/restapi/qris/show_qris.php*' => Http::response([
                'status' => 'success',
                'data' => [
                    'qris_content' => '000201...',
                    'qris_request_date' => now()->format('Y-m-d H:i:s'),
                    'qris_invoiceid' => 'INV-DUP-TEST',
                    'qris_nmid' => 'NMID123',
                ],
            ], 200),
        ]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $package = TopupPackage::create(['name' => '10 Token', 'tokens' => 10, 'price_idr' => 10000, 'is_active' => true]);

        $this->actingAs($user1)->post(route('wallet.checkout', $package));
        $this->actingAs($user2)->post(route('wallet.checkout', $package));

        $order1 = PaymentOrder::where('user_id', $user1->id)->firstOrFail();
        $order2 = PaymentOrder::where('user_id', $user2->id)->firstOrFail();

        $this->assertNotEquals($order1->amount_idr, $order2->amount_idr);
        $this->assertNotEquals($order1->unique_code, $order2->unique_code);
        $this->assertSame(10000, $order1->base_amount_idr);
        $this->assertSame(10000, $order2->base_amount_idr);
    }

    public function test_concurrent_order_allocations_do_not_produce_duplicate_amounts(): void
    {
        config([
            'qris.min_unique_code' => 1,
            'qris.max_unique_code' => 50,
        ]);

        $service = app(UniquePaymentAmountService::class);
        $allocatedAmounts = [];

        for ($i = 1; $i <= 20; $i++) {
            $allocation = $service->allocate(10000, "ORDER-CONCURRENT-{$i}");
            $this->assertNotContains($allocation['final_amount'], $allocatedAmounts);
            $this->assertSame($allocation['final_amount'], $allocation['base_amount'] + $allocation['unique_code']);
            $allocatedAmounts[] = $allocation['final_amount'];
        }

        $this->assertCount(20, array_unique($allocatedAmounts));
    }

    public function test_payments_with_mismatched_amounts_are_rejected_by_webhook(): void
    {
        $user = User::factory()->create();
        $order = PaymentOrder::create([
            'order_id' => 'QR-MISMATCH-TEST',
            'user_id' => $user->id,
            'base_amount_idr' => 10000,
            'unique_code' => 150,
            'amount_idr' => 10150,
            'tokens' => 10,
            'status' => 'pending',
            'payment_provider' => 'qris',
            'qris_invoice_id' => 'INV-MISMATCH',
        ]);

        // Attacker or user paid standard 10000 without the 150 unique code
        $mismatchedPayload = [
            'invoice' => 'INV-MISMATCH',
            'qris_status' => 'success',
            'amount' => 10000, // Expected: 10150
            'qris_paid_date' => '2026-10-29 20:05:00',
        ];

        $response = $this->postJson(route('payments.qris.webhook'), $mismatchedPayload);

        $response->assertStatus(422)
            ->assertJson(['status' => 'error']);

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(0, (int) ($user->wallet?->balance ?? 0));
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_qris_webhook_credits_tokens_idempotently(): void
    {
        config([
            'qris.base_url' => 'https://qris.interactive.co.id/restapi/qris/',
            'qris.api_key' => 'test-api-key',
            'qris.merchant_id' => 'MID-TEST-99',
        ]);

        Http::fake([
            'https://qris.interactive.co.id/restapi/qris/checkpaid_qris.php*' => Http::response([
                'status' => 'success',
                'data' => [
                    'qris_status' => 'paid',
                    'qris_payment_customername' => 'Provider Confirmed',
                    'qris_payment_methodby' => 'BCA',
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $order = PaymentOrder::create([
            'order_id' => 'QR-TEST-ORDER-101',
            'user_id' => $user->id,
            'base_amount_idr' => 10000,
            'unique_code' => 123,
            'amount_idr' => 10123,
            'tokens' => 10,
            'status' => 'pending',
            'payment_provider' => 'qris',
            'qris_invoice_id' => 'INV-101',
        ]);

        $webhookPayload = [
            'invoice' => 'INV-101',
            'qris_status' => 'success',
            'amount' => 10123,
            'qris_paid_date' => '2026-10-29 20:05:00',
            'qris_payment_methodby' => 'BCA',
            'qris_payment_customername' => 'Ahmad Dani',
            'rrn' => '9988776655',
        ];

        // First call
        $firstResponse = $this->postJson(route('payments.qris.webhook'), $webhookPayload);
        $firstResponse->assertOk()->assertJson(['status' => 'ok']);

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('BCA', $order->fresh()->qris_payment_methodby);
        $this->assertSame('Provider Confirmed', $order->fresh()->qris_customer_name);
        $this->assertSame(10, (int) $user->wallet()->firstOrFail()->balance);
        $this->assertDatabaseCount('wallet_transactions', 1);
        Http::assertSent(function (ClientRequest $request): bool {
            return str_contains($request->url(), 'checkpaid_qris.php')
                && $request['invid'] === 'INV-101'
                && (int) $request['trxvalue'] === 10123
                && $request['mID'] === 'MID-TEST-99';
        });

        // Duplicate call (idempotent verification)
        $secondResponse = $this->postJson(route('payments.qris.webhook'), $webhookPayload);
        $secondResponse->assertOk();

        $this->assertSame(10, (int) $user->wallet()->firstOrFail()->balance);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_success_webhook_does_not_credit_tokens_when_provider_does_not_confirm_payment(): void
    {
        config([
            'qris.base_url' => 'https://qris.interactive.co.id/restapi/qris/',
            'qris.api_key' => 'test-api-key',
            'qris.merchant_id' => 'MID-TEST-99',
        ]);

        Http::fake([
            'https://qris.interactive.co.id/restapi/qris/checkpaid_qris.php*' => Http::response([
                'status' => 'failed',
                'data' => ['qris_status' => 'unpaid'],
            ], 200),
        ]);

        $user = User::factory()->create();
        $order = PaymentOrder::create([
            'order_id' => 'QR-WEBHOOK-UNVERIFIED',
            'user_id' => $user->id,
            'base_amount_idr' => 10000,
            'unique_code' => 123,
            'amount_idr' => 10123,
            'tokens' => 10,
            'status' => 'pending',
            'payment_provider' => 'qris',
            'qris_invoice_id' => 'INV-WEBHOOK-UNVERIFIED',
            'qris_request_date' => now(),
        ]);

        $response = $this->postJson(route('payments.qris.webhook'), [
            'invoice' => 'INV-WEBHOOK-UNVERIFIED',
            'qris_status' => 'success',
            'amount' => 10123,
        ]);

        $response->assertOk();
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(0, (int) ($user->wallet?->balance ?? 0));
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_repeated_manual_status_checks_do_not_duplicate_token_credits(): void
    {
        config([
            'qris.base_url' => 'https://qris.interactive.co.id/restapi/qris/',
            'qris.api_key' => 'test-api-key',
            'qris.merchant_id' => 'MID-TEST-99',
            'qris.check_status_cooldown_seconds' => 0, // Disable cooldown for repeat test
        ]);

        Http::fake([
            'https://qris.interactive.co.id/restapi/qris/checkpaid_qris.php*' => Http::response([
                'status' => 'success',
                'data' => [
                    'qris_status' => 'paid',
                    'qris_payment_customername' => 'Budi Santoso',
                    'qris_payment_methodby' => 'GOPAY',
                ],
                'qris_api_version_code' => '2.1',
            ], 200),
        ]);

        $user = User::factory()->create();
        $order = PaymentOrder::create([
            'order_id' => 'QR-MANUAL-CHECK-202',
            'user_id' => $user->id,
            'base_amount_idr' => 20000,
            'unique_code' => 147,
            'amount_idr' => 20147,
            'tokens' => 20,
            'status' => 'pending',
            'payment_provider' => 'qris',
            'qris_invoice_id' => 'INV-202',
            'qris_request_date' => now()->subMinutes(5),
        ]);

        // First manual check
        $first = $this->actingAs($user)->post(route('wallet.orders.check-status', $order));
        $first->assertRedirect(route('wallet.orders.show', $order));

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(20, (int) $user->wallet()->firstOrFail()->balance);
        $this->assertDatabaseCount('wallet_transactions', 1);

        // Second manual check
        $second = $this->actingAs($user)->post(route('wallet.orders.check-status', $order));
        $second->assertSessionHas('notice', 'Pembayaran sudah terverifikasi dan token telah ditambahkan ke wallet Anda.');

        $this->assertSame(20, (int) $user->wallet()->firstOrFail()->balance);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_expired_orders_cannot_credit_tokens(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-29 12:00:00'));

        $user = User::factory()->create();
        $order = PaymentOrder::create([
            'order_id' => 'QR-EXPIRED-TEST',
            'user_id' => $user->id,
            'base_amount_idr' => 10000,
            'unique_code' => 186,
            'amount_idr' => 10186,
            'tokens' => 10,
            'status' => 'pending',
            'payment_provider' => 'qris',
            'qris_invoice_id' => 'INV-EXP',
            'qris_request_date' => Carbon::parse('2026-10-29 11:25:00'), // 35 minutes ago (> 30 mins)
        ]);

        // Attempt manual check on expired order
        $response = $this->actingAs($user)->post(route('wallet.orders.check-status', $order));
        $this->assertSame('expired', $order->fresh()->status);
        $response->assertSessionHasErrors('payment');

        // Attempt webhook on expired order
        $webhookResponse = $this->postJson(route('payments.qris.webhook'), [
            'invoice' => 'INV-EXP',
            'qris_status' => 'success',
            'amount' => 10186,
        ]);
        $webhookResponse->assertStatus(400);

        $this->assertSame('expired', $order->fresh()->status);
        $this->assertSame(0, (int) ($user->wallet?->balance ?? 0));
        $this->assertDatabaseCount('wallet_transactions', 0);

        Carbon::setTestNow();
    }

    public function test_manual_check_status_enforces_cooldown_rate_limit(): void
    {
        config([
            'qris.base_url' => 'https://qris.interactive.co.id/restapi/qris/',
            'qris.api_key' => 'test-api-key',
            'qris.merchant_id' => 'MID-TEST-99',
            'qris.check_status_cooldown_seconds' => 10,
        ]);

        Http::fake();

        $user = User::factory()->create();
        $order = PaymentOrder::create([
            'order_id' => 'QR-COOLDOWN-TEST',
            'user_id' => $user->id,
            'base_amount_idr' => 10000,
            'unique_code' => 111,
            'amount_idr' => 10111,
            'tokens' => 10,
            'status' => 'pending',
            'payment_provider' => 'qris',
            'qris_invoice_id' => 'INV-COOL',
            'qris_request_date' => now()->subMinutes(2),
            'last_checked_at' => now()->subSeconds(3), // Checked 3 seconds ago (cooldown is 10s)
        ]);

        $response = $this->actingAs($user)->post(route('wallet.orders.check-status', $order));

        $response->assertSessionHas('cooldown_warning');
        Http::assertNothingSent();
    }
}
