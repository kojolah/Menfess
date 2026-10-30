<?php

namespace Tests\Feature;

use App\Livewire\Menfess\CreateMenfess;
use App\Models\Category;
use App\Models\ContentFilterException;
use App\Models\ContentFilterRule;
use App\Models\Menfess;
use App\Models\PublicationJob;
use App\Models\User;
use App\Models\Wallet;
use App\Services\MenfessContentFilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class MenfessContentFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_text_without_a_matching_rule_is_preserved_exactly(): void
    {
        ContentFilterRule::factory()->create(['phrase' => 'goblok']);

        $result = app(MenfessContentFilterService::class)->filter('Akademik dan kesehatan reproduksi.');

        $this->assertSame('Akademik dan kesehatan reproduksi.', $result->body);
        $this->assertSame(0, $result->censoredCount);
    }

    public function test_censors_registered_profanity_and_explicit_phrases(): void
    {
        ContentFilterRule::factory()->create(['phrase' => 'goblok']);
        ContentFilterRule::factory()->create(['phrase' => 'seks eksplisit']);

        $result = app(MenfessContentFilterService::class)->filter('GOBLOK! bahas seks, eksplisit.');

        $this->assertSame('****! bahas ****.', $result->body);
        $this->assertSame(2, $result->censoredCount);
    }

    public function test_detects_leetspeak_and_punctuation_without_matching_inside_other_words(): void
    {
        ContentFilterRule::factory()->create(['phrase' => 'kontol']);

        $service = app(MenfessContentFilterService::class);
        $this->assertSame('****!!!', $service->filter('K.0.N.T.O.L!!!')->body);
        $this->assertSame('Kontrol kontolase assignment', $service->filter('Kontrol kontolase assignment')->body);
    }

    public function test_exception_can_suppress_a_registered_match(): void
    {
        ContentFilterRule::factory()->create(['phrase' => 'ass']);
        ContentFilterException::factory()->create(['phrase' => 'ass health']);

        $result = app(MenfessContentFilterService::class)->filter('ass health then ass!');

        $this->assertSame('ass health then ****!', $result->body);
    }

    public function test_urls_mentions_hashtags_and_html_text_without_a_match_remain_plain_text(): void
    {
        ContentFilterRule::factory()->create(['phrase' => 'goblok']);

        $text = 'https://kampus.example/path @akun #akademik <script>alert(1)</script>';

        $this->assertSame($text, app(MenfessContentFilterService::class)->filter($text)->body);
    }

    public function test_submission_censors_then_queues_publication_without_manual_approval(): void
    {
        Queue::fake();
        ContentFilterRule::factory()->create(['phrase' => 'goblok']);
        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id, 'balance' => 1]);

        Livewire::actingAs($user)->test(CreateMenfess::class)
            ->set('body', 'Pesan goblok untuk kampus')
            ->set('categoryId', $this->category()->id)
            ->set('platforms', ['instagram', 'x'])
            ->call('submit')
            ->assertHasNoErrors();

        $menfess = Menfess::firstOrFail();
        $this->assertSame('Pesan **** untuk kampus', $menfess->body);
        $this->assertSame('approved', $menfess->status);
        $this->assertSame(1, $menfess->censored_count);
        $this->assertDatabaseCount('publication_jobs', 2);
    }

    public function test_content_containing_personal_contact_details_is_not_auto_published(): void
    {
        Queue::fake();

        Livewire::actingAs(User::factory()->create())->test(CreateMenfess::class)
            ->set('body', 'Hubungi saya di campus@example.edu')
            ->set('categoryId', $this->category()->id)
            ->set('platforms', ['instagram'])
            ->call('submit')
            ->assertHasNoErrors();

        $menfess = Menfess::firstOrFail();
        $this->assertSame('moderation_pending', $menfess->status);
        $this->assertSame('needs_review', $menfess->filter_status);
        $this->assertDatabaseCount('publication_jobs', 0);
    }

    public function test_explicit_threat_rule_routes_content_to_human_review_without_publishing(): void
    {
        Queue::fake();
        ContentFilterRule::factory()->create(['phrase' => 'aku akan membunuhmu', 'category' => 'other', 'action' => 'review']);

        Livewire::actingAs(User::factory()->create())->test(CreateMenfess::class)
            ->set('body', 'Aku akan membunuhmu jika datang')
            ->set('categoryId', $this->category()->id)
            ->set('platforms', ['instagram'])
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertSame('moderation_pending', Menfess::firstOrFail()->status);
        $this->assertDatabaseCount('publication_jobs', 0);
    }

    public function test_instagram_free_slot_is_reserved_once_and_other_pending_posts_use_tokens(): void
    {
        Queue::fake();
        config(['menfess.instagram_free_posts_per_day' => 2, 'menfess.instagram_free_cooldown_minutes' => 0]);
        $user = User::factory()->create();
        $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 4]);
        $categoryId = $this->category()->id;

        foreach (['Pertama', 'Kedua', 'Ketiga'] as $text) {
            Livewire::actingAs($user)->test(CreateMenfess::class)
                ->set('body', $text.' kiriman kampus')
                ->set('categoryId', $categoryId)
                ->set('platforms', ['instagram'])
                ->call('submit')
                ->assertHasNoErrors();
        }

        $jobs = PublicationJob::orderBy('id')->get();
        $this->assertSame(['pending', 'pending', 'pending'], $jobs->pluck('status')->all());
        $this->assertSame(1, $jobs->filter(fn (PublicationJob $job): bool => (bool) $job->tokens_reserved === false)->count());
        $this->assertSame(0, (int) $wallet->fresh()->balance);
        $this->assertDatabaseCount('publication_usage', 3);
    }

    public function test_filter_management_is_limited_to_super_admin(): void
    {
        $this->actingAs(User::factory()->publisher()->create())
            ->get(route('admin.content-filters.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('admin.content-filters.index'))
            ->assertOk()
            ->assertSee('Filter konten otomatis');
    }

    public function test_super_admin_can_add_and_disable_a_filter_rule(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->post(route('admin.content-filters.rules.store'), [
            'phrase' => 'customword', 'category' => 'profanity', 'action' => 'censor',
        ])->assertRedirect();

        $rule = ContentFilterRule::where('phrase', 'customword')->firstOrFail();
        $this->assertSame('****!', app(MenfessContentFilterService::class)->filter('CUSTOMWORD!')->body);

        $this->put(route('admin.content-filters.rules.update', $rule), [
            'phrase' => 'customword', 'category' => 'profanity', 'action' => 'censor', 'is_active' => 0,
        ])->assertRedirect();
        $this->assertSame('CUSTOMWORD!', app(MenfessContentFilterService::class)->filter('CUSTOMWORD!')->body);
    }

    private function category(): Category
    {
        return Category::create(['name' => 'umum!', 'slug' => 'umum', 'badge_color' => '#F7ECCD', 'is_active' => true]);
    }
}
