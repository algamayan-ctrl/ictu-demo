<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CanonicalHostRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TrustProxies::flushState();

        parent::tearDown();
    }

    private function canonical(string $url): void
    {
        config(['app.url' => $url, 'app.canonical_redirect' => true]);
        URL::forceRootUrl($url);
        URL::forceScheme((string) parse_url($url, PHP_URL_SCHEME));
    }

    public function test_local_127_0_0_1_redirects_to_the_configured_localhost_origin_with_its_query(): void
    {
        $this->canonical('http://localhost:8080');

        $this->get('http://127.0.0.1:8080/login')
            ->assertStatus(302)
            ->assertHeader('Location', 'http://localhost:8080/login');

        $this->get('http://127.0.0.1:8080/reset-password/abc123?email=user%40cspc.edu.ph&x=1')
            ->assertRedirect('http://localhost:8080/reset-password/abc123?email=user%40cspc.edu.ph&x=1');

        $this->call('HEAD', 'http://127.0.0.1:8080/')
            ->assertRedirect('http://localhost:8080/');
    }

    public function test_the_canonical_host_is_served_with_the_origin_relative_assets_unchanged(): void
    {
        $this->canonical('http://localhost:8080');

        $this->get('http://localhost:8080/login')
            ->assertOk()
            ->assertSee('href="/vendor/bootstrap/bootstrap.min.css"', false)
            ->assertSee('src="/images/spmu-acpmp-logo.png"', false);
    }

    public function test_the_destination_comes_from_app_url_never_from_the_request(): void
    {
        $this->canonical('http://localhost:8080');

        $response = $this->get('http://evil.example/login', ['Host' => 'evil.example']);
        $response->assertStatus(302)->assertHeader('Location', 'http://localhost:8080/login');

        /* A protocol-relative looking path still lands on the canonical host. */
        $hostile = new \Illuminate\Http\Request([], [], [], [], [], [
            'REQUEST_METHOD' => 'GET',
            'HTTP_HOST' => 'evil.example',
            'REQUEST_URI' => '//evil.example/steal?a=1',
            'QUERY_STRING' => 'a=1',
        ]);
        $location = (new \App\Http\Middleware\RedirectToCanonicalHost)
            ->handle($hostile, fn () => response('served'))
            ->headers->get('Location');
        $this->assertSame('http://localhost:8080/evil.example/steal?a=1', $location);
        $this->assertSame('localhost', parse_url($location, PHP_URL_HOST));

        /* Forwarded host headers from an untrusted client are ignored. */
        $this->get('http://localhost:8080/login', ['X-Forwarded-Host' => 'evil.example'])->assertOk();
        $this->get('http://app/login', ['X-Forwarded-Host' => 'localhost'])
            ->assertRedirect('http://localhost:8080/login');
    }

    public function test_canonical_production_urls_are_served_and_aliases_redirect_to_https(): void
    {
        $this->canonical('https://spmu-acpmp.tech');

        $this->get('https://spmu-acpmp.tech/login')->assertOk();
        $this->get('https://spmu-acpmp.tech/login?error=1')->assertOk();

        $this->get('http://203.0.113.10/login?a=b')
            ->assertRedirect('https://spmu-acpmp.tech/login?a=b');
    }

    public function test_an_https_proxy_never_causes_a_redirect_loop(): void
    {
        $this->canonical('https://spmu-acpmp.tech');

        /* TLS terminated at the proxy and no TRUSTED_PROXIES: plain http with the public Host is served. */
        $this->get('http://spmu-acpmp.tech/login')->assertOk();

        /* A trusted proxy (e.g. the Cloudflare tunnel) forwarding the public host and scheme is served. */
        TrustProxies::at('172.30.0.5');
        $this->withServerVariables(['REMOTE_ADDR' => '172.30.0.5'])
            ->get('http://app/login', [
                'X-Forwarded-Host' => 'spmu-acpmp.tech',
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Port' => '443',
            ])
            ->assertOk();
    }

    public function test_form_submissions_on_another_host_are_refused_without_signing_in_or_saving(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->canonical('http://localhost:8080');
        $user = User::factory()->create(['password' => 'correct-horse-battery-staple']);
        $users = User::query()->count();

        $this->post('http://127.0.0.1:8080/login', [
            'email' => $user->email,
            'password' => 'correct-horse-battery-staple',
        ])
            ->assertStatus(421)
            ->assertSee('http://localhost:8080/', false)
            ->assertHeaderMissing('Location');
        $this->assertGuest();

        $this->post('http://127.0.0.1:8080/register', [
            'email' => 'new.person@cspc.edu.ph',
            'password' => 'Another-Secure-2026!',
            'password_confirmation' => 'Another-Secure-2026!',
        ])->assertStatus(421);
        $this->assertSame($users, User::query()->count());

        $this->actingAs($user)->put('http://127.0.0.1:8080/profile', ['name' => 'Changed Elsewhere'])->assertStatus(421);
        $this->actingAs($user)->delete('http://127.0.0.1:8080/profile/picture')->assertStatus(421);
        $this->assertNotSame('Changed Elsewhere', $user->fresh()->name);
        $this->app['auth']->forgetGuards();

        /* The same sign-in on the canonical host is unaffected. */
        $this->post('http://localhost:8080/login', [
            'email' => $user->email,
            'password' => 'correct-horse-battery-staple',
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_signed_links_and_the_google_callback_keep_their_query_on_the_canonical_host(): void
    {
        $this->canonical('http://localhost:8080');

        $signed = URL::temporarySignedRoute('login', now()->addMinutes(5), ['probe' => 1]);
        $this->assertStringStartsWith('http://localhost:8080/login?', $signed);

        $alias = str_replace('http://localhost:8080', 'http://127.0.0.1:8080', $signed);
        $this->get($alias)->assertRedirect($signed);

        $this->get('http://127.0.0.1:8080/auth/google/callback?state=s&code=c')
            ->assertRedirect('http://localhost:8080/auth/google/callback?state=s&code=c');
    }

    public function test_health_checks_by_ip_are_not_redirected(): void
    {
        $this->canonical('http://localhost:8080');

        $this->get('http://127.0.0.1/up')->assertOk();
        $this->get('http://127.0.0.1:8080/up')->assertOk();
    }

    public function test_the_redirect_is_off_unless_enabled(): void
    {
        config(['app.url' => 'https://spmu-acpmp.tech', 'app.canonical_redirect' => false]);

        $this->get('http://203.0.113.10/login')->assertOk();
    }
}
