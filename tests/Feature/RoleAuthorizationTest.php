<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Menfess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('adminRouteRoleCases')]
    public function test_admin_routes_enforce_the_role_matrix(string $role, string $routeName, int $expectedStatus): void
    {
        $user = match ($role) {
            'moderator' => User::factory()->moderator()->create(),
            'publisher' => User::factory()->publisher()->create(),
            'super_admin' => User::factory()->superAdmin()->create(),
            default => User::factory()->create(),
        };

        $this->actingAs($user)->get(route($routeName))->assertStatus($expectedStatus);
    }

    public static function adminRouteRoleCases(): array
    {
        return [
            'user blocked from admin dashboard' => ['user', 'admin.dashboard', 403],
            'user blocked from moderation' => ['user', 'admin.moderation.index', 403],
            'user blocked from publication center' => ['user', 'admin.publications.index', 403],
            'moderator can open dashboard' => ['moderator', 'admin.dashboard', 200],
            'moderator can moderate' => ['moderator', 'admin.moderation.index', 200],
            'moderator cannot publish' => ['moderator', 'admin.publications.index', 403],
            'publisher can open dashboard' => ['publisher', 'admin.dashboard', 200],
            'publisher cannot moderate' => ['publisher', 'admin.moderation.index', 403],
            'publisher can manage publications' => ['publisher', 'admin.publications.index', 200],
            'super admin can open dashboard' => ['super_admin', 'admin.dashboard', 200],
            'super admin can moderate' => ['super_admin', 'admin.moderation.index', 200],
            'super admin can manage publications' => ['super_admin', 'admin.publications.index', 200],
            'unknown role is denied by default' => ['observer', 'admin.dashboard', 403],
        ];
    }

    public function test_publisher_cannot_view_unapproved_menfess_but_can_view_approved_content(): void
    {
        $owner = User::factory()->create();
        $publisher = User::factory()->publisher()->create();
        $category = Category::create([
            'name' => 'umum!',
            'slug' => 'umum',
            'badge_color' => '#F7ECCD',
        ]);
        $menfess = Menfess::create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'body' => 'Konten privat sampai lolos moderasi.',
            'platforms' => ['instagram'],
            'status' => 'moderation_pending',
        ]);

        $this->actingAs($publisher)->get(route('menfesses.show', $menfess))->assertNotFound();

        $menfess->update(['status' => 'approved']);
        $this->get(route('menfesses.show', $menfess))->assertOk();
    }

    public function test_disabled_user_is_logged_out_before_accessing_protected_pages(): void
    {
        $this->actingAs(User::factory()->disabled()->create())
            ->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('account');

        $this->assertGuest();
    }
}
