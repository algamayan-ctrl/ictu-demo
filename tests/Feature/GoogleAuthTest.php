<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

/**
 * Google sign-in proves identity; SPMU-ACPMP still decides access.
 *
 * Socialite is mocked throughout - no test reaches Google.
 */
class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
            'services.google.allowed_domains' => '',
        ]);
    }

    /** Pretend Google returned this verified profile. */
    private function fakeGoogleUser(string $email, string $name = 'Google Person', ?bool $verified = true, string $subject = '1234567890'): void
    {
        // Existing-login fixtures represent a prior password-confirmed link,
        // never an identity created by the callback under test.
        if ($local = User::query()->whereRaw('LOWER(email) = ?', [strtolower($email)])->first()) {
            \App\Models\OauthIdentity::query()->create(['user_id' => $local->id, 'provider' => 'google', 'subject' => $subject ?: 'previous-subject']);
        }
        $socialiteUser = (new SocialiteUser)->map([
            'id' => $subject,
            'name' => $name,
            'email' => $email,
            'avatar' => 'https://example.test/avatar.png',
        ]);
        $socialiteUser->setRaw(['sub' => $subject, 'email' => $email, 'email_verified' => $verified]);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_unverified_google_email_cannot_authenticate_an_existing_account(): void
    {
        $user = User::factory()->create(['email' => 'unverified@cspc.edu.ph']);
        $this->fakeGoogleUser($user->email, verified: false);
        $this->get(route('auth.google.callback'))->assertRedirect(route('home'))->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    public function test_missing_google_email_verification_cannot_authenticate_an_existing_account(): void
    {
        $user = User::factory()->create(['email' => 'missing@cspc.edu.ph']);
        $this->fakeGoogleUser($user->email, verified: null);
        $this->get(route('auth.google.callback'))->assertRedirect(route('home'))->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    public function test_missing_google_subject_cannot_authenticate_an_existing_account(): void
    {
        $user = User::factory()->create(['email' => 'subject@cspc.edu.ph']);
        $this->fakeGoogleUser($user->email, subject: '');
        $this->get(route('auth.google.callback'))->assertRedirect(route('home'))->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    public function test_provider_exception_details_cannot_expose_oauth_secrets_in_logs(): void
    {
        \Illuminate\Support\Facades\Log::spy();
        Socialite::shouldReceive('driver')->with('google')->andThrow(new \RuntimeException('client_secret=DO-NOT-LOG token=PRIVATE'));
        $this->get(route('auth.google.callback'))->assertRedirect(route('home'))->assertSessionHasErrors('google');
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->with('Google sign-in failed.', ['exception' => \RuntimeException::class])->once();
        $this->assertGuest();
    }

    /* ------------------------------------------------------------------ */
    /* Local login is untouched                                            */
    /* ------------------------------------------------------------------ */

    public function test_local_email_and_password_login_still_works(): void
    {
        $user = User::factory()->create(['password' => 'correct-horse-battery-staple']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-horse-battery-staple',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('BORROWER', session('active_workspace'));
    }

    public function test_login_page_keeps_google_option_visible_when_configuration_is_pending(): void
    {
        config(['services.google.client_id' => '', 'services.google.client_secret' => '', 'services.google.redirect' => '']);

        $response = $this->get('/login');

        $response->assertOk()
            ->assertSee('Email')
            ->assertSee('Continue with Google');
    }

    public function test_login_page_shows_the_google_button_when_configured(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Continue with Google')
            ->assertSee('Keep me signed in');
    }

    /* ------------------------------------------------------------------ */
    /* Redirect                                                            */
    /* ------------------------------------------------------------------ */

    public function test_google_redirect_route_sends_the_visitor_to_google(): void
    {
        $this->get(route('auth.google.redirect'))
            ->assertRedirectContains('accounts.google.com')
            ->assertRedirectContains('prompt=select_account');
    }

    public function test_google_redirect_is_refused_when_oauth_is_not_configured(): void
    {
        config(['services.google.client_id' => '', 'services.google.client_secret' => '', 'services.google.redirect' => '']);

        $this->get(route('auth.google.redirect'))
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
    }

    /* ------------------------------------------------------------------ */
    /* Successful sign-in reuses the existing local account                */
    /* ------------------------------------------------------------------ */

    public function test_existing_active_borrower_signs_in_through_google(): void
    {
        $user = User::factory()->create([
            'email' => 'borrower.person@cspc.edu.ph',
            'access_classification' => AccessClassification::BorrowerOnly,
        ]);

        $this->fakeGoogleUser('borrower.person@cspc.edu.ph');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('BORROWER', session('active_workspace'));
        $this->assertSame(
            AccessClassification::BorrowerOnly,
            $user->fresh()->access_classification
        );
    }

    public function test_email_matching_is_case_insensitive_and_creates_no_second_account(): void
    {
        $user = User::factory()->create(['email' => 'mixed.case@cspc.edu.ph']);

        $this->fakeGoogleUser('Mixed.Case@CSPC.edu.ph');

        $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::query()->count());
        $this->assertSame('mixed.case@cspc.edu.ph', $user->fresh()->email);
    }

    public function test_spmu_action_officer_keeps_the_spmu_workspace(): void
    {
        $user = User::factory()->create([
            'email' => 'officer@cspc.edu.ph',
            'access_classification' => AccessClassification::SpmuOfficer,
        ]);

        $this->fakeGoogleUser('officer@cspc.edu.ph');

        $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('SPMU', session('active_workspace'));
        $this->assertSame(
            AccessClassification::SpmuOfficer,
            $user->fresh()->access_classification
        );
    }

    public function test_spmu_head_keeps_the_spmu_workspace(): void
    {
        $user = User::factory()->create([
            'email' => 'head@cspc.edu.ph',
            'access_classification' => AccessClassification::SpmuHead,
        ]);

        $this->fakeGoogleUser('head@cspc.edu.ph');

        $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('SPMU', session('active_workspace'));
        $this->assertSame(
            AccessClassification::SpmuHead,
            $user->fresh()->access_classification
        );
    }

    /* ------------------------------------------------------------------ */
    /* Rejections                                                          */
    /* ------------------------------------------------------------------ */

    public function test_unknown_google_email_opens_registration_without_provisioning_privileges(): void
    {
        /* Only a CSPC Employee address opens Google registration; students and External Borrowers are refused. */
        $this->fakeGoogleUser('employee.not.registered@cspc.edu.ph');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('register', ['source' => 'google']));

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
        $this->assertDatabaseMissing('users', ['email' => 'employee.not.registered@cspc.edu.ph']);
    }

    public function test_inactive_local_account_cannot_sign_in_through_google(): void
    {
        User::factory()->create([
            'email' => 'inactive@cspc.edu.ph',
            'account_status' => AccountStatus::Inactive,
        ]);

        $this->fakeGoogleUser('inactive@cspc.edu.ph');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
    }

    public function test_suspended_local_account_cannot_sign_in_through_google(): void
    {
        User::factory()->create([
            'email' => 'suspended@cspc.edu.ph',
            'account_status' => AccountStatus::Suspended,
        ]);

        $this->fakeGoogleUser('suspended@cspc.edu.ph');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
    }

    public function test_account_without_a_portal_classification_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'retired@cspc.edu.ph']);

        /* Written raw: the retired value is deliberately not portal-enabled. */
        User::query()->whereKey($user->id)->update([
            'access_classification' => AccessClassification::RetiredInactive->value,
        ]);

        $this->fakeGoogleUser('retired@cspc.edu.ph');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
    }

    public function test_google_returning_no_email_is_rejected(): void
    {
        $this->fakeGoogleUser('');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
    }

    public function test_cancelled_google_consent_returns_to_login(): void
    {
        $this->get(route('auth.google.callback', ['error' => 'access_denied']))
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
    }

    /* ------------------------------------------------------------------ */
    /* Domain restriction is configuration, not hardcoded                  */
    /* ------------------------------------------------------------------ */

    public function test_allowed_domain_is_accepted_when_configured(): void
    {
        config(['services.google.allowed_domains' => 'cspc.edu.ph']);

        $user = User::factory()->create(['email' => 'staff@cspc.edu.ph']);

        $this->fakeGoogleUser('staff@cspc.edu.ph');

        $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_disallowed_domain_is_rejected_even_for_an_existing_user(): void
    {
        config(['services.google.allowed_domains' => 'cspc.edu.ph']);

        User::factory()->create(['email' => 'outsider@gmail.com']);

        $this->fakeGoogleUser('outsider@gmail.com');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
    }

    public function test_blank_domain_configuration_imposes_no_domain_requirement(): void
    {
        config(['services.google.allowed_domains' => '']);

        $user = User::factory()->create(['email' => 'partner@example.org']);

        $this->fakeGoogleUser('partner@example.org');

        $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    /* ------------------------------------------------------------------ */
    /* Google never writes to the local record                             */
    /* ------------------------------------------------------------------ */

    public function test_google_profile_never_overwrites_local_account_data(): void
    {
        $unit = \App\Models\OrganizationalUnit::query()->create([
            'unit_code' => 'TEST-UNIT',
            'unit_name' => 'Test Unit',
            'unit_type' => 'OFFICE',
            'active' => true,
        ]);

        $user = User::factory()->create([
            'email' => 'protected@cspc.edu.ph',
            'full_name' => 'Local Recorded Name',
            'employee_no' => 'EMP-00042',
            'designation' => 'Property Custodian',
            'organizational_unit_id' => $unit->id,
            'access_classification' => AccessClassification::SpmuOfficer,
            'password' => 'local-password-value',
        ]);

        $originalPassword = $user->fresh()->password;

        $this->fakeGoogleUser('protected@cspc.edu.ph', 'Google Display Name');

        $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

        $fresh = $user->fresh();

        $this->assertSame('Local Recorded Name', $fresh->full_name);
        $this->assertSame('EMP-00042', $fresh->employee_no);
        $this->assertSame('Property Custodian', $fresh->designation);
        $this->assertSame($unit->id, $fresh->organizational_unit_id);
        $this->assertSame(AccessClassification::SpmuOfficer, $fresh->access_classification);
        $this->assertSame($user->employment_type, $fresh->employment_type);
        $this->assertSame($originalPassword, $fresh->password);
    }

    public function test_google_sign_in_records_the_login_timestamp(): void
    {
        $user = User::factory()->create([
            'email' => 'stamped@cspc.edu.ph',
            'last_login_at' => null,
        ]);

        $this->fakeGoogleUser('stamped@cspc.edu.ph');

        $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_home_and_compatibility_login_render_the_same_landing_page(): void
    {
        $home = $this->get('/')->assertOk();
        $login = $this->get('/login')->assertOk();

        $this->assertSame($home->getContent(), $login->getContent());
        $css = preg_replace('/\s+/', '', $home->getContent());
        $this->assertStringContainsString(
            'linear-gradient(90deg,rgba(255,255,255,.98)0%,rgba(255,255,255,.96)30%,rgba(255,255,255,.84)48%,rgba(255,255,255,.42)66%,rgba(255,255,255,.10)100%),url("http://localhost/images/cspc-campus-hero.png")centerright/coverno-repeat;',
            $css
        );
        $this->assertSame(1, substr_count($css, 'images/cspc-campus-hero.png'));
        $home->assertSee('class="landing-shell"', false)
            ->assertSee('images/cspc-campus-hero.png')
            ->assertSee('class="landing-login-slot login-page"', false)
            ->assertDontSee('GOOGLE LOGIN BACKGROUND SYNC');
    }

    public function test_rejected_google_domain_returns_to_the_landing_card_and_refresh_keeps_it(): void
    {
        config(['services.google.allowed_domains' => 'cspc.edu.ph']);
        $this->fakeGoogleUser('outsider@gmail.com');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('google');

        $message = 'This Google account is not from an allowed domain. Contact the SPMU Admin for account access assistance.';
        $this->withCookie(config('session.cookie'), session()->getId());
        $page = $this->get('/')->assertOk()
            ->assertSeeInOrder(['class="landing-shell"', 'class="login-card"', 'class="login-google"', 'class="google-auth-error"', $message, 'Need help?'], false);
        $this->assertSame(1, substr_count($page->getContent(), $message));
        $this->get('/')->assertOk()->assertSee('class="landing-shell"', false)->assertDontSee($message);
        $this->assertGuest();
    }

    public function test_oauth_exception_displays_on_the_landing_page(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andThrow(new \RuntimeException('Provider unavailable'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get(route('auth.google.callback'))->assertRedirect(route('home'))->assertSessionHasErrors('google');
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->get('/')->assertOk()->assertSeeInOrder([
            'class="landing-shell"', 'class="login-card"', 'Google sign-in could not be completed. Please try again.',
        ], false);
    }

    public function test_password_validation_and_invalid_credentials_keep_the_landing_card(): void
    {
        foreach (['/', '/login'] as $origin) {
            $this->from($origin)->post('/login', ['email' => 'staff@cspc.edu.ph'])
                ->assertRedirect($origin)->assertSessionHasErrors('password');
            $this->withCookie(config('session.cookie'), session()->getId());
            $this->get($origin)->assertOk()->assertSee('class="landing-shell"', false)
                ->assertSee('The password field is required.')->assertSee('staff@cspc.edu.ph');
        }

        $user = User::factory()->create(['password' => 'correct-password']);
        $this->from('/')->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertRedirect('/')->assertSessionHasErrors('password');
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->get('/')->assertOk()->assertSeeInOrder([
            'class="landing-shell"', 'class="login-card"', 'Incorrect password. Please try again.',
        ], false);
        $this->assertGuest();
    }

    public function test_session_notices_and_general_errors_are_inside_the_shared_card(): void
    {
        $this->withSession([
            'status' => 'Session status message.',
            'error' => 'Session error message.',
            'errors' => ['default' => ['format' => ':message', 'messages' => [
                'authentication' => ['General authentication error.'],
            ]]],
        ])->get('/')->assertOk()->assertSeeInOrder([
            'class="landing-shell"', 'class="login-card"', 'Session status message.',
            'Session error message.', 'General authentication error.',
        ], false);
    }
}
