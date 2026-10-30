<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Menfess;
use App\Models\PaymentOrder;
use App\Models\TopupPackage;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_can_list_all_users_and_open_user_details(): void
    {
        $user = User::factory()->create(['name' => 'Akun kampus', 'email' => 'akun@example.test']);

        $this->actingAs(User::factory()->moderator()->create())
            ->get(route('admin.users.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('akun@example.test')
            ->assertSee(route('admin.users.show', $user));

        $this->get(route('admin.users.show', $user))->assertOk()->assertSee('DETAIL AKUN');
    }

    public function test_user_detail_displays_topup_orders_wallet_ledger_and_menfess_history(): void
    {
        $user = User::factory()->create();
        $package = TopupPackage::create(['name' => 'Regular', 'price_idr' => 10000, 'tokens' => 25, 'is_active' => true]);
        PaymentOrder::create([
            'order_id' => 'TOPUP-DETAIL-001',
            'user_id' => $user->id,
            'topup_package_id' => $package->id,
            'amount_idr' => 10000,
            'tokens' => 25,
            'status' => 'settlement',
            'paid_at' => now(),
        ]);
        $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 25]);
        $wallet->transactions()->create([
            'type' => 'topup', 'amount' => 25, 'balance_before' => 0, 'balance_after' => 25,
            'idempotency_key' => 'test-topup-detail-001', 'reason' => 'Pembayaran Midtrans terverifikasi',
        ]);
        $menfess = $this->menfess($user, 'Riwayat isi menfess anonim.');

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('admin.users.show', $user))
            ->assertOk()
            ->assertSee('TOPUP-DETAIL-001')
            ->assertSee('Regular')
            ->assertSee('Pembayaran Midtrans terverifikasi')
            ->assertSee($menfess->public_number)
            ->assertSee('Riwayat isi menfess anonim.');
    }

    public function test_super_admin_can_remove_a_users_menfess_with_audited_reason_and_file_cleanup(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('uploads/source.jpg', 'image');
        Storage::disk('public')->put('menfess/generated.png', 'png');
        $admin = User::factory()->superAdmin()->create();
        $owner = User::factory()->create();
        $menfess = $this->menfess($owner, 'Konten yang melanggar aturan kampus.');
        $menfess->attachments()->create(['disk' => 'public', 'path' => 'uploads/source.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 5]);
        $menfess->generatedImages()->create(['platform' => 'instagram', 'width' => 1080, 'height' => 1350, 'disk' => 'public', 'path' => 'menfess/generated.png', 'metadata' => []]);

        $this->actingAs($admin)->delete(route('admin.users.menfesses.destroy', [$owner, $menfess]), [
            'reason' => 'Menyebarkan informasi pribadi pengguna lain.',
        ])->assertRedirect()->assertSessionHas('notice');

        $this->assertDatabaseMissing('menfesses', ['id' => $menfess->id]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'menfess.deleted_by_super_admin',
            'subject_type' => Menfess::class,
            'subject_id' => $menfess->id,
        ]);
        $this->assertSame('Menyebarkan informasi pribadi pengguna lain.', AuditLog::latest('id')->firstOrFail()->metadata['reason']);
        Storage::disk('public')->assertMissing('uploads/source.jpg');
        Storage::disk('public')->assertMissing('menfess/generated.png');
    }

    public function test_super_admin_cannot_delete_a_menfess_through_the_wrong_user_url(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $menfess = $this->menfess($owner, 'Kiriman yang tetap milik pemiliknya.');

        $this->actingAs(User::factory()->superAdmin()->create())
            ->delete(route('admin.users.menfesses.destroy', [$otherUser, $menfess]), ['reason' => 'Alasan valid untuk uji keamanan.'])
            ->assertNotFound();

        $this->assertDatabaseHas('menfesses', ['id' => $menfess->id, 'user_id' => $owner->id]);
    }

    private function menfess(User $user, string $body): Menfess
    {
        $category = Category::firstOrCreate(['slug' => 'umum'], ['name' => 'umum!', 'badge_color' => '#F7ECCD', 'is_active' => true]);

        return Menfess::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'body' => $body,
            'platforms' => ['instagram'],
            'status' => 'published',
            'public_number' => 'MENFESS-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
        ]);
    }
}
