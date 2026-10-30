<?php

namespace Tests\Feature;

use App\Livewire\Menfess\CreateMenfess;
use App\Models\Category;
use App\Models\Menfess;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MenfessFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_is_redirected_from_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_user_submission_passes_automatic_checks_and_creates_separate_publication_jobs(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id, 'balance' => 3]);
        $category = $this->createCategory();

        Livewire::actingAs($user)
            ->test(CreateMenfess::class)
            ->set('body', 'Semangat untuk semua yang sedang ujian!')
            ->set('categoryId', $category->id)
            ->set('platforms', ['instagram', 'x'])
            ->call('submit')
            ->assertHasNoErrors();

        $menfess = Menfess::firstOrFail();

        $this->assertSame($user->id, $menfess->user_id);
        $this->assertSame('approved', $menfess->status);
        $this->assertSame('passed', $menfess->filter_status);
        $this->assertSame('MENFESS-000001', $menfess->public_number);
        $this->assertSame(['instagram', 'x'], $menfess->platforms);
        $this->assertDatabaseCount('publication_jobs', 2);
    }

    public function test_new_menfess_keeps_its_selected_canvas_background_from_configured_palette(): void
    {
        Queue::fake();
        config(['menfess.canvas_backgrounds' => ['#F6DDE8', '#DCEBFA', '#DDF2E5']]);
        $user = User::factory()->create();
        $category = $this->createCategory();

        $component = Livewire::actingAs($user)->test(CreateMenfess::class);
        $this->assertContains($component->get('canvasBackground'), config('menfess.canvas_backgrounds'));

        $component->set('canvasBackground', '#DCEBFA')
            ->set('body', 'Preview dan gambar final memakai warna yang sama.')
            ->set('categoryId', $category->id)
            ->set('platforms', ['instagram'])
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertSame('#DCEBFA', Menfess::firstOrFail()->metadata['canvas_background']);
    }

    public function test_user_cannot_submit_text_over_the_configured_limit(): void
    {
        $user = User::factory()->create();
        $category = $this->createCategory();

        Livewire::actingAs($user)
            ->test(CreateMenfess::class)
            ->set('body', str_repeat('a', 1001))
            ->set('categoryId', $category->id)
            ->set('platforms', ['instagram'])
            ->call('submit')
            ->assertHasErrors(['body' => 'max']);

        $this->assertDatabaseCount('menfesses', 0);
    }

    public function test_user_cannot_submit_an_empty_menfess(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(CreateMenfess::class)
            ->set('categoryId', $this->createCategory()->id)
            ->set('platforms', ['instagram'])
            ->call('submit')
            ->assertHasErrors(['body' => 'required']);

        $this->assertDatabaseCount('menfesses', 0);
    }

    public function test_user_cannot_select_an_inactive_category(): void
    {
        $category = $this->createCategory();
        $category->update(['is_active' => false]);

        Livewire::actingAs(User::factory()->create())
            ->test(CreateMenfess::class)
            ->set('body', 'Isi menfess valid untuk uji kategori.')
            ->set('categoryId', $category->id)
            ->set('platforms', ['instagram'])
            ->call('submit')
            ->assertHasErrors(['categoryId' => 'exists']);

        $this->assertDatabaseCount('menfesses', 0);
    }

    public function test_user_cannot_submit_to_an_unsupported_platform(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(CreateMenfess::class)
            ->set('body', 'Isi menfess valid untuk uji platform.')
            ->set('categoryId', $this->createCategory()->id)
            ->set('platforms', ['facebook'])
            ->call('submit')
            ->assertHasErrors(['platforms.0' => 'in']);

        $this->assertDatabaseCount('menfesses', 0);
    }

    public function test_user_cannot_upload_an_svg_as_a_menfess_image(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(CreateMenfess::class)
            ->set('body', 'Isi menfess valid untuk uji unggahan.')
            ->set('categoryId', $this->createCategory()->id)
            ->set('platforms', ['instagram'])
            ->set('image', UploadedFile::fake()->create('payload.svg', 10, 'image/svg+xml'))
            ->call('submit')
            ->assertHasErrors(['image']);

        $this->assertDatabaseCount('menfesses', 0);
    }

    public function test_uploaded_menfess_photo_is_kept_on_private_storage_not_the_public_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Queue::fake();
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(CreateMenfess::class)
            ->set('body', 'Semangat untuk semua yang sedang ujian!')
            ->set('categoryId', $this->createCategory()->id)
            ->set('platforms', ['instagram'])
            ->set('image', UploadedFile::fake()->image('campus-photo.jpg'))
            ->call('submit')
            ->assertHasNoErrors();

        $attachment = Menfess::firstOrFail()->attachments()->firstOrFail();

        $this->assertSame('local', $attachment->disk);
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_user_cannot_read_another_users_private_draft(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $draft = Menfess::create([
            'user_id' => $owner->id,
            'category_id' => $this->createCategory()->id,
            'body' => 'Cerita yang masih privat',
            'platforms' => ['instagram'],
            'status' => 'draft',
        ]);

        $this->actingAs($otherUser)->get(route('menfesses.show', $draft))->assertNotFound();
    }

    public function test_private_attachment_is_served_only_to_a_user_allowed_to_view_the_menfess(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $menfess = Menfess::create([
            'user_id' => $owner->id,
            'category_id' => $this->createCategory()->id,
            'body' => 'Draft dengan gambar privat.',
            'platforms' => ['instagram'],
            'status' => 'draft',
        ]);
        $contents = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lZsAAAAASUVORK5CYII=', true);
        Storage::disk('local')->put('menfess/1/private.png', $contents);
        $attachment = $menfess->attachments()->create([
            'disk' => 'local',
            'path' => 'menfess/1/private.png',
            'mime_type' => 'image/png',
            'size_bytes' => strlen($contents),
        ]);

        $this->actingAs($owner)
            ->get(route('menfess-attachments.show', $attachment))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->actingAs($otherUser)
            ->get(route('menfess-attachments.show', $attachment))
            ->assertNotFound();
    }

    public function test_user_cannot_read_another_users_published_menfess_from_history_detail(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $published = Menfess::create([
            'user_id' => $owner->id,
            'category_id' => $this->createCategory()->id,
            'body' => 'Konten publik yang tetap tidak menjadi riwayat akun lain.',
            'platforms' => ['instagram'],
            'status' => 'published',
        ]);

        $this->actingAs($otherUser)->get(route('menfesses.show', $published))->assertNotFound();
    }

    public function test_user_menfess_history_only_contains_the_authenticated_users_submissions(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = $this->createCategory();
        Menfess::create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'body' => 'Riwayat milik akun sendiri.',
            'platforms' => ['instagram'],
            'status' => 'draft',
        ]);
        Menfess::create([
            'user_id' => $otherUser->id,
            'category_id' => $category->id,
            'body' => 'Riwayat privat milik akun lain.',
            'platforms' => ['instagram'],
            'status' => 'published',
        ]);

        $this->actingAs($owner)
            ->get(route('menfesses.index'))
            ->assertOk()
            ->assertSee('Riwayat milik akun sendiri.')
            ->assertDontSee('Riwayat privat milik akun lain.');
    }

    public function test_menfess_detail_escapes_user_supplied_html(): void
    {
        $owner = User::factory()->create();
        $menfess = Menfess::create([
            'user_id' => $owner->id,
            'category_id' => $this->createCategory()->id,
            'body' => '<script>alert("xss")</script>',
            'platforms' => ['instagram'],
            'status' => 'draft',
        ]);

        $this->actingAs($owner)
            ->get(route('menfesses.show', $menfess))
            ->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>', false);
    }

    public function test_moderator_approval_creates_a_separate_job_for_each_platform(): void
    {
        Queue::fake();
        $moderator = User::factory()->create(['role' => 'moderator']);
        $owner = User::factory()->create();
        Wallet::create(['user_id' => $owner->id, 'balance' => 3]);
        $menfess = Menfess::create([
            'user_id' => $owner->id,
            'category_id' => $this->createCategory()->id,
            'body' => 'Cerita yang menunggu review',
            'platforms' => ['instagram', 'x'],
            'status' => 'moderation_pending',
            'submitted_at' => now(),
        ]);

        $this->actingAs($moderator)
            ->post(route('admin.moderation.approve', $menfess))
            ->assertRedirect();

        $this->assertSame('approved', $menfess->fresh()->status);
        $this->assertDatabaseCount('publication_jobs', 2);
        $this->assertDatabaseHas('publication_jobs', ['menfess_id' => $menfess->id, 'platform' => 'instagram', 'status' => 'pending']);
        $this->assertDatabaseHas('publication_jobs', ['menfess_id' => $menfess->id, 'platform' => 'x', 'status' => 'pending']);
    }

    public function test_regular_user_is_forbidden_from_admin_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_moderator_can_open_operational_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'moderator']))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Ringkasan operasional')
            ->assertSee('bukan laba');
    }

    public function test_user_dashboard_only_lists_the_signed_in_users_menfesses(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = $this->createCategory();
        Menfess::create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'body' => 'Konten pribadi milik pemilik.',
            'platforms' => ['instagram'],
            'status' => 'draft',
        ]);
        Menfess::create([
            'user_id' => $otherUser->id,
            'category_id' => $category->id,
            'body' => 'Rahasia pengguna lain.',
            'platforms' => ['instagram'],
            'status' => 'draft',
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Konten pribadi milik pemilik.')
            ->assertDontSee('Rahasia pengguna lain.');
    }

    private function createCategory(): Category
    {
        return Category::create([
            'name' => 'umum!',
            'slug' => 'umum',
            'badge_color' => '#F7ECCD',
            'is_active' => true,
        ]);
    }
}
