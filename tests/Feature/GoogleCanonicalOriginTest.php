<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Tests\TestCase;

class GoogleCanonicalOriginTest extends TestCase
{
    use RefreshDatabase;

    public function test_alias_origin_is_redirected_before_google_state_is_created(): void
    {
        config(['app.url' => 'http://localhost:8080', 'services.google.client_id' => 'test-client',
            'services.google.client_secret' => 'test-secret', 'services.google.redirect' => 'http://localhost:8080/auth/google/callback']);
        Socialite::shouldReceive('driver')->never();

        $this->get('http://127.0.0.1:8080/auth/google')
            ->assertRedirect('http://localhost:8080/auth/google');

        $this->assertNull(session('state'));
        $this->assertGuest();
    }

    public function test_application_callback_mismatch_is_rejected_before_provider_redirect(): void
    {
        config(['app.url' => 'http://localhost:8080', 'services.google.client_id' => 'test-client',
            'services.google.client_secret' => 'test-secret', 'services.google.redirect' => 'http://localhost:8765/auth/google/callback']);
        Socialite::shouldReceive('driver')->never();

        $this->get('http://localhost:8080/auth/google')->assertSessionHasErrors('google');

        $this->assertNull(session('state'));
        $this->assertGuest();
    }

    public function test_matching_canonical_origin_starts_the_real_socialite_authorization_redirect(): void
    {
        config(['app.url' => 'https://spmu.example.test', 'services.google.client_id' => 'test-client',
            'services.google.client_secret' => 'test-secret', 'services.google.redirect' => 'https://spmu.example.test/auth/google/callback']);

        $response = $this->get('https://spmu.example.test/auth/google');
        $response->assertRedirectContains('accounts.google.com');
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $parameters);
        $this->assertSame('https://spmu.example.test/auth/google/callback', $parameters['redirect_uri']);
        $this->assertNotEmpty($parameters['state']);
        $this->assertSame($parameters['state'], session('state'));
        $this->assertGuest();
    }
}
