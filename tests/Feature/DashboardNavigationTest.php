<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Menfess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_the_mobile_menu_and_reach_real_public_routes(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('logo.png')
            ->assertSeeHtml('aria-controls="mobile-navigation"')
            ->assertSeeHtml('x-show="open"')
            ->assertSeeHtml('@click="open = false"')
            ->assertSee(route('rules'))
            ->assertSee(route('login'));
    }

    #[DataProvider('navigationRoleCases')]
    public function test_dashboard_navigation_matches_the_users_role(string $role, array $visibleRoutes, array $hiddenRoutes): void
    {
        $user = match ($role) {
            'moderator' => User::factory()->moderator()->create(),
            'publisher' => User::factory()->publisher()->create(),
            'super_admin' => User::factory()->superAdmin()->create(),
            default => User::factory()->create(),
        };

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('YOUR VOICE, YOUR SPACE.')
            ->assertSee('Hai, '.Str::of($user->name)->explode(' ')->first().'!');

        foreach ($visibleRoutes as $routeName) {
            $response->assertSee(route($routeName));
        }

        foreach ($hiddenRoutes as $routeName) {
            $response->assertDontSee(route($routeName));
        }
    }

    public static function navigationRoleCases(): array
    {
        return [
            'regular user' => [
                'user',
                ['dashboard', 'menfesses.create', 'menfesses.index', 'wallet.index', 'rules'],
                ['admin.dashboard', 'admin.moderation.index', 'admin.publications.index', 'admin.users.index'],
            ],
            'moderator' => [
                'moderator',
                ['admin.dashboard', 'admin.moderation.index'],
                ['admin.publications.index', 'admin.users.index', 'admin.content-filters.index'],
            ],
            'publisher' => [
                'publisher',
                ['admin.dashboard', 'admin.publications.index'],
                ['admin.moderation.index', 'admin.users.index', 'admin.content-filters.index'],
            ],
            'super admin' => [
                'super_admin',
                ['admin.dashboard', 'admin.moderation.index', 'admin.publications.index', 'admin.users.index', 'admin.content-filters.index'],
                [],
            ],
        ];
    }

    public function test_dashboard_platform_totals_only_count_the_signed_in_users_menfesses(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = Category::create([
            'name' => 'umum!',
            'slug' => 'umum-dashboard',
            'badge_color' => '#F7ECCD',
            'is_active' => true,
        ]);

        Menfess::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'body' => 'Kiriman tujuan ganda milik sendiri.',
            'platforms' => ['instagram', 'x'],
            'status' => 'published',
            'public_number' => 'MENFESS-000001',
        ]);
        Menfess::create([
            'user_id' => $otherUser->id,
            'category_id' => $category->id,
            'body' => 'Kiriman pengguna lain tidak boleh dihitung.',
            'platforms' => ['instagram'],
            'status' => 'published',
            'public_number' => 'MENFESS-000002',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $viewData = $response->original->getData();

        $response->assertOk()
            ->assertSee('Kiriman tujuan ganda milik sendiri.')
            ->assertDontSee('Kiriman pengguna lain tidak boleh dihitung.');
        $this->assertSame(1, $viewData['menfessCount']);
        $this->assertSame(1, $viewData['instagramCount']);
        $this->assertSame(1, $viewData['xCount']);
    }

    public function test_empty_dashboard_shows_a_link_to_create_the_first_menfess(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('No stories shared yet.')
            ->assertSee('Mulai kirim pikiranmu lewat Unwarfess.')
            ->assertSee(route('menfesses.create'));
    }
}
