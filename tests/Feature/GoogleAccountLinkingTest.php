<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\OauthIdentity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class GoogleAccountLinkingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.client_id' => 'synthetic-client', 'services.google.client_secret' => 'synthetic-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback', 'services.google.allowed_domains' => '']);
    }

    private function identity(string $email, string $subject = 'google-subject-1'): void
    {
        $identity = (new GoogleUser)->map(['id' => $subject, 'email' => $email, 'name' => 'Provider Name']);
        $identity->setRaw(['sub' => $subject, 'email_verified' => true, 'email' => $email]);
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($identity);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    private function flow(User $user): array
    {
        return ['google_link' => ['user_id' => $user->id, 'expires_at' => now()->addMinutes(10)->timestamp], 'active_workspace' => 'BORROWER'];
    }

    public function test_matching_email_alone_cannot_silently_link_or_sign_in(): void
    {
        $user = User::factory()->create();
        $this->identity($user->email);
        $this->get(route('auth.google.callback'))->assertRedirect(route('home'))->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertDatabaseCount('oauth_identities', 0);
    }

    public function test_password_confirmed_session_links_exactly_one_subject_and_records_audit(): void
    {
        $user = User::factory()->create();
        $this->identity($user->email);
        $this->actingAs($user)->withSession($this->flow($user))->get(route('auth.google.callback'))
            ->assertRedirect(route('profile.show'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('oauth_identities', ['user_id' => $user->id, 'subject' => 'google-subject-1']);
        $this->assertDatabaseCount('oauth_identities', 1);
        $this->assertDatabaseHas('audit_events', ['action_code' => 'GOOGLE_ACCOUNT_LINKED', 'actor_user_id' => $user->id]);
        $this->assertSame($user->full_name, $user->fresh()->full_name);
        $this->assertFalse(session()->has('google_link'));
    }

    public function test_link_start_requires_current_password(): void
    {
        $user = User::factory()->create(['password' => 'correct-local-password']);
        $this->actingAs($user)->post(route('auth.google.link'), ['password' => 'wrong-password'])->assertSessionHasErrors('password');
        $this->assertFalse(session()->has('google_link'));
        $this->assertDatabaseCount('oauth_identities', 0);
    }

    public function test_link_start_requires_local_login(): void
    {
        $this->post(route('auth.google.link'), ['password' => 'anything'])->assertRedirect(route('login'));
        $this->assertDatabaseCount('oauth_identities', 0);
    }

    public function test_linking_cannot_cross_local_accounts(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->identity($user->email);
        $this->actingAs($user)->withSession($this->flow($other))->get(route('auth.google.callback'))
            ->assertRedirect(route('profile.show'))->assertSessionHasErrors('google');
        $this->assertDatabaseCount('oauth_identities', 0);
    }

    public function test_linking_requires_the_registered_email_even_for_verified_google_accounts(): void
    {
        $user = User::factory()->create();
        $this->identity('different@gmail.com');
        $this->actingAs($user)->withSession($this->flow($user))->get(route('auth.google.callback'))->assertSessionHasErrors('google');
        $this->assertDatabaseCount('oauth_identities', 0);
    }

    public function test_expired_linking_session_does_not_write_an_identity(): void
    {
        $user = User::factory()->create();
        $this->identity($user->email);
        $this->actingAs($user)->withSession(['google_link' => ['user_id' => $user->id, 'expires_at' => now()->subSecond()->timestamp]])
            ->get(route('auth.google.callback'))->assertSessionHasErrors('google');
        $this->assertDatabaseCount('oauth_identities', 0);
    }

    public function test_an_identity_cannot_be_reassigned_to_another_user(): void
    {
        $owner = User::factory()->create();
        $user = User::factory()->create();
        OauthIdentity::query()->create(['user_id' => $owner->id, 'provider' => 'google', 'subject' => 'google-subject-1']);
        $this->identity($user->email);
        $this->actingAs($user)->withSession($this->flow($user))->get(route('auth.google.callback'))->assertSessionHasErrors('google');
        $this->assertDatabaseCount('oauth_identities', 1);
        $this->assertSame($owner->id, OauthIdentity::query()->sole()->user_id);
    }

    public function test_linked_external_account_keeps_local_authority_and_password_fallback(): void
    {
        $user = User::factory()->create(['email' => 'external@agency.gov.ph', 'password' => 'local-password']);
        OauthIdentity::query()->create(['user_id' => $user->id, 'provider' => 'google', 'subject' => 'google-subject-1']);
        $this->identity($user->email);
        $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('BORROWER', session('active_workspace'));
        $this->assertDatabaseCount('users', 1);
    }

    public function test_inactive_linked_account_is_still_refused(): void
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Inactive]);
        OauthIdentity::query()->create(['user_id' => $user->id, 'provider' => 'google', 'subject' => 'google-subject-1']);
        $this->identity($user->email);
        $this->get(route('auth.google.callback'))->assertRedirect(route('home'))->assertSessionHasErrors('google');
        $this->assertGuest();
    }
}
