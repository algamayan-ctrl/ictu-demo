<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Tests\TestCase;

class AuthLiveAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_resend_is_one_delivery_alert_and_never_creates_an_otp(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        config(['mail.default' => 'log']);
        Mail::fake();
        $user = User::factory()->create(['email_verified_at' => null]);
        $csrf = 'auth-live-synthetic-csrf';
        $this->withSession(['_token' => $csrf])->post(route('account.verification.resend'), [
            'email' => $user->email, '_token' => $csrf,
        ])->assertSessionHasErrors('delivery');

        $response = $this->view('auth.verify-account', ['errors' => session('errors')]);
        $this->assertSame(1, substr_count((string) $response, 'The verification OTP could not be sent.'));
        $response->assertSee('role="alert"', false);
        $this->assertDatabaseMissing('email_verification_otps', ['user_id' => $user->id]);
        Mail::assertNothingSent();
    }

    public function test_invalid_otp_is_rendered_once_with_accessible_field_feedback(): void
    {
        $this->post(route('account.verification.verify'), [
            'email' => 'unknown@example.test', 'otp' => '123456',
        ])->assertSessionHasErrors('otp');
        $message = session('errors')->first('otp');
        $response = $this->view('auth.verify-account', ['errors' => session('errors')]);
        $this->assertSame(1, substr_count((string) $response, e($message)));
        $response->assertSee('id="otp-error"', false)->assertSee('aria-describedby="otp-error"', false);
        $this->assertGuest();
    }

    public function test_invalid_email_and_code_both_keep_their_own_single_error(): void
    {
        $this->post(route('account.verification.verify'), [
            'email' => 'invalid', 'otp' => '12',
        ])->assertSessionHasErrors(['email', 'otp']);
        $messages = session('errors');
        $response = $this->view('auth.verify-account', ['errors' => $messages]);
        foreach (['email', 'otp'] as $field) {
            $this->assertSame(1, substr_count((string) $response, e($messages->first($field))));
        }
        $response->assertSee('id="verification-email-error"', false)->assertSee('id="otp-error"', false);
    }

    public function test_unconfigured_google_fallback_applies_to_external_and_employee_users(): void
    {
        config(['services.google.client_id' => '', 'services.google.client_secret' => '']);
        $this->get(route('auth.google.redirect'))->assertSessionHasErrors([
            'google' => 'Google sign-in is not configured on this server. Use your email and password.',
        ]);
        $this->assertGuest();
        $this->assertDatabaseCount('oauth_identities', 0);
    }

    public function test_smtp_probe_authenticates_without_sending_and_keeps_live_evidence_unverified(): void
    {
        $this->configureProbe();
        $transport = Mockery::mock(SmtpTransport::class);
        $transport->shouldReceive('start')->once();
        $transport->shouldReceive('stop')->once();
        Mail::shouldReceive('mailer->getSymfonyTransport')->once()->andReturn($transport);

        $this->assertSame(0, Artisan::call('spmu:auth-readiness', ['--json' => true, '--check-smtp' => true]));
        $output = Artisan::output();
        $result = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('ACCEPTED; NO EMAIL SENT', $result['smtp_test']);
        $this->assertSame('NOT TESTED', $result['email_test']);
        $this->assertContains('Actual OTP and reset email receipt', $result['not_verified']);
        $this->assertStringNotContainsString('synthetic-private-client-secret', $output);
    }

    public function test_smtp_probe_failure_is_non_secret_and_cannot_pass_its_gate(): void
    {
        $this->configureProbe();
        $transport = Mockery::mock(SmtpTransport::class);
        $transport->shouldReceive('start')->once()->andThrow(new \RuntimeException('Do not expose synthetic-private-client-secret'));
        $transport->shouldReceive('stop')->once();
        Mail::shouldReceive('mailer->getSymfonyTransport')->once()->andReturn($transport);

        $this->assertSame(1, Artisan::call('spmu:auth-readiness', ['--json' => true, '--check-smtp' => true]));
        $output = Artisan::output();
        $result = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('NOT READY', $result['gate']);
        $this->assertStringStartsWith('FAILED:', $result['smtp_test']);
        $this->assertStringNotContainsString('synthetic-private-client-secret', $output);
    }

    private function configureProbe(): void
    {
        config(['app.url' => 'http://localhost', 'services.google.client_id' => 'synthetic-client-id',
            'services.google.client_secret' => 'synthetic-private-client-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
            'services.google.allowed_domains' => '', 'mail.default' => 'smtp',
            'mail.from.address' => 'sender@example.test', 'session.driver' => 'file']);
    }
}
