<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_login_redirect_uses_the_configured_socialite_provider(): void
    {
        config([
            'services.google.client_id' => 'oauth-client-id',
            'services.google.client_secret' => 'oauth-client-secret',
        ]);
        Socialite::fake('google');

        $this->get(route('auth.google'))
            ->assertRedirect('https://socialite.fake/google/authorize');
    }

    public function test_missing_google_credentials_show_setup_guidance(): void
    {
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

        $this->from(route('login'))
            ->get(route('auth.google'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['oauth' => 'Login Google belum dikonfigurasi. Pengelola perlu menambahkan OAuth Client ID dan Client Secret.']);
    }

    public function test_verified_google_identity_creates_a_user_and_starts_a_session(): void
    {
        Socialite::fake('google', $this->googleUser('google-subject-1', 'STUDENT@EXAMPLE.EDU', true));

        $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

        $user = User::where('google_id', 'google-subject-1')->firstOrFail();
        $this->assertSame('student@example.edu', $user->email);
        $this->assertSame('user', $user->role);
        $this->assertSame('active', $user->status);
        $this->assertAuthenticatedAs($user);
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        Socialite::fake('google', $this->googleUser('google-subject-2', 'student@example.edu', false));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['oauth' => 'Akun Google harus memiliki email yang sudah terverifikasi.']);

        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_google_login_does_not_implicitly_link_an_existing_email_account(): void
    {
        User::factory()->create(['email' => 'student@example.edu', 'google_id' => null]);
        Socialite::fake('google', $this->googleUser('google-subject-3', 'student@example.edu', true));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('oauth');

        $this->assertDatabaseCount('users', 1);
        $this->assertGuest();
    }

    public function test_disabled_google_account_cannot_start_a_session(): void
    {
        User::factory()->create([
            'email' => 'student@example.edu',
            'google_id' => 'google-subject-4',
            'status' => 'disabled',
        ]);
        Socialite::fake('google', $this->googleUser('google-subject-4', 'student@example.edu', true));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['oauth' => 'Akun ini sedang dinonaktifkan. Hubungi pengelola kampus.']);

        $this->assertGuest();
    }

    public function test_logout_invalidates_the_authenticated_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_mass_assignment_cannot_change_sensitive_account_fields(): void
    {
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $user->fill([
            'role' => 'super_admin',
            'status' => 'disabled',
            'google_id' => 'attacker-controlled-id',
            'email' => 'attacker@example.edu',
        ]);

        $this->assertSame('user', $user->role);
        $this->assertSame('active', $user->status);
        $this->assertNull($user->google_id);
        $this->assertNotSame('attacker@example.edu', $user->email);
    }

    private function googleUser(string $googleId, string $email, bool $verified): GoogleUser
    {
        $user = GoogleUser::fake([
            'id' => $googleId,
            'email' => $email,
            'name' => 'Campus Student',
        ]);
        $user->setRaw(['email_verified' => $verified]);

        return $user;
    }
}
