<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Models\User;
use App\Support\StaticAsset;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Pages opened through a hostname other than APP_URL (127.0.0.1 instead of
 * localhost, or a proxy alias) must load their own CSS, scripts and images:
 * the 'self' Content-Security-Policy blocks any other origin.
 */
class StaticAssetOriginTest extends TestCase
{
    use RefreshDatabase;

    private const CANONICAL = 'http://localhost:8080';

    protected function setUp(): void
    {
        parent::setUp();

        /* The same forced root AppServiceProvider applies from APP_URL. */
        /* Asset URLs are checked as rendered on each hostname, so the canonical-host redirect stays off here. */
        config(['app.url' => self::CANONICAL, 'app.canonical_redirect' => false]);
        URL::forceRootUrl(self::CANONICAL);
        URL::forceScheme('http');
    }

    public function test_login_page_assets_are_origin_relative_on_every_hostname(): void
    {
        foreach (['http://127.0.0.1:8080', 'http://localhost:8080', 'https://spmu-acpmp.tech'] as $origin) {
            $html = $this->get($origin.'/login')->assertOk()->getContent();

            $this->assertMatchesRegularExpression('#href="/css/app\.css\?v=\d+"#', $html, "{$origin}: app.css keeps its version");
            $this->assertStringContainsString('href="/vendor/bootstrap/bootstrap.min.css"', $html);
            $this->assertStringContainsString('src="/images/spmu-acpmp-logo.png"', $html);
            $this->assertMatchesRegularExpression("#url\\(['\"]/images/cspc-campus-hero\\.png['\"]\\)#", $html);
            $this->assertMatchesRegularExpression('#src="/js/app\.js\?v=\d+"#', $html);
            $this->assertDoesNotMatchRegularExpression('#(href|src)="https?://[^"]+/(css|js|vendor|images)/#', $html, "{$origin}: no absolute static asset URL");
            $this->assertDoesNotMatchRegularExpression("#url\\(['\"]?https?://#", $html);

            $this->assertReferencedFilesExist($html);
        }
    }

    public function test_authenticated_layout_assets_are_origin_relative(): void
    {
        $this->seed(DatabaseSeeder::class);
        $head = User::query()->where('access_classification', AccessClassification::SpmuHead->value)->firstOrFail();

        $html = $this->withSession(['active_workspace' => 'SPMU'])
            ->actingAs($head)
            ->get('http://127.0.0.1:8080/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('href="/images/cspc-logo.png"', $html);
        $this->assertMatchesRegularExpression('#href="/css/app\.css\?v=\d+"#', $html);
        $this->assertDoesNotMatchRegularExpression('#(href|src)="https?://[^"]+/(css|js|vendor|images)/#', $html);
        $this->assertReferencedFilesExist($html);
    }

    public function test_security_sensitive_urls_keep_the_canonical_origin(): void
    {
        $this->get('http://127.0.0.1:8080/login')->assertOk();

        /* Emails, OAuth, signed links and redirects still use APP_URL. */
        $this->assertSame(self::CANONICAL.'/login', route('login'));
        $this->assertStringStartsWith(self::CANONICAL.'/', URL::temporarySignedRoute('login', now()->addMinutes(5)));
        $this->assertSame(self::CANONICAL.'/images/cspc-seal-document.png', asset('images/cspc-seal-document.png'));
    }

    public function test_the_helper_never_uses_the_host_header_and_keeps_a_subdirectory_base(): void
    {
        $this->get('http://evil.example/login', ['Host' => 'evil.example'])->assertOk()
            ->assertDontSee('evil.example/css', false);

        $this->assertSame('/css/app.css', StaticAsset::url('css/app.css'));
        $this->assertSame('/images/cspc-logo.png', StaticAsset::url('/images/cspc-logo.png'));

        $this->app->instance('request', \Illuminate\Http\Request::create(
            'http://localhost/spmu/index.php/login', 'GET', [], [], [], [
                'SCRIPT_NAME' => '/spmu/index.php',
                'SCRIPT_FILENAME' => public_path('index.php'),
            ]
        ));
        $this->assertSame('/spmu/css/app.css', StaticAsset::url('css/app.css'));
    }

    /** Every same-application asset the page references is a real public file. */
    private function assertReferencedFilesExist(string $html): void
    {
        preg_match_all('#(?:href|src)="(/(?:css|js|vendor|images)/[^"?]+)#', $html, $tags);
        preg_match_all("#url\\(['\"]?(/images/[^'\")?]+)#", $html, $backgrounds);

        $paths = array_unique(array_merge($tags[1], $backgrounds[1]));
        $this->assertNotEmpty($paths);

        foreach ($paths as $path) {
            $this->assertFileExists(public_path(ltrim($path, '/')), "{$path} must exist under public/");
            $this->assertGreaterThan(0, filesize(public_path(ltrim($path, '/'))));
        }
    }
}
