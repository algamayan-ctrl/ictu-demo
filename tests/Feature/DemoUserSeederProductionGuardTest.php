<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Demo accounts share a password that is published in the source code, so a
 * production environment never receives them, even when SEED_DEMO_USERS is
 * switched on by mistake.
 */
class DemoUserSeederProductionGuardTest extends TestCase
{
    use RefreshDatabase;

    private const DEMO_EMAILS = [
        'borrower@spmu.test',
        'spmu@spmu.test',
        'spmu-head@spmu.test',
        'ictu@spmu.test',
    ];

    /**
     * Seeds through the real command with --force, as a deployment does. In a
     * production environment the plain seed() helper stops at the
     * "are you sure" confirmation instead.
     *
     * @param  class-string  $class
     */
    private function seedForced(string $class): void
    {
        $exitCode = $this->withoutMockingConsoleOutput()->artisan('db:seed', ['--class' => $class, '--force' => true]);

        $this->assertSame(0, $exitCode);
    }

    public function test_production_never_receives_demo_accounts_even_with_the_flag_on(): void
    {
        $this->app['env'] = 'production';
        config(['app.seed_demo_users' => true]);

        Log::spy();

        $this->seedForced(DatabaseSeeder::class);

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, User::query()->whereIn('email', self::DEMO_EMAILS)->count());

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message): bool => str_contains($message, 'Demo accounts were NOT created'));
    }

    public function test_the_refusal_does_not_stop_the_reference_data_from_being_seeded(): void
    {
        $this->app['env'] = 'production';
        config(['app.seed_demo_users' => true]);

        $this->seedForced(DatabaseSeeder::class);

        $this->assertDatabaseHas('roles', ['role_code' => 'BORROWER', 'active' => true]);
        $this->assertDatabaseHas('roles', ['role_code' => 'SPMU', 'active' => true]);
        $this->assertDatabaseHas('roles', ['role_code' => 'ICTU', 'active' => true]);
        $this->assertDatabaseHas('organizational_units', ['unit_code' => 'ICTU']);
        $this->assertDatabaseHas('system_settings', ['setting_key' => 'max_upload_mb']);
        $this->assertGreaterThan(0, \App\Models\InventoryItem::query()->count());
    }

    public function test_production_with_the_flag_off_creates_no_accounts_and_logs_nothing(): void
    {
        $this->app['env'] = 'production';
        config(['app.seed_demo_users' => false]);

        Log::spy();

        $this->seedForced(DatabaseSeeder::class);

        $this->assertSame(0, User::query()->count());
        Log::shouldNotHaveReceived('warning');
    }

    public function test_running_the_demo_seeder_directly_in_production_is_refused_too(): void
    {
        $this->app['env'] = 'production';
        config(['app.seed_demo_users' => false]);
        $this->seedForced(DatabaseSeeder::class);

        config(['app.seed_demo_users' => true]);
        $this->seedForced(DemoUserSeeder::class);

        $this->assertSame(0, User::query()->count());
    }

    public function test_an_existing_account_is_not_touched_by_the_refusal(): void
    {
        config(['app.seed_demo_users' => false]);
        $this->seedForced(DatabaseSeeder::class);

        /* A real account that happens to use a demo address must keep its own password. */
        $existing = User::factory()->create([
            'email' => 'ictu@spmu.test',
            'password' => Hash::make('A-Real-Password-2026!'),
        ]);
        $hash = $existing->getRawOriginal('password');

        $this->app['env'] = 'production';
        config(['app.seed_demo_users' => true]);
        $this->seedForced(DemoUserSeeder::class);

        $this->assertSame(1, User::query()->count());
        $this->assertSame($hash, $existing->fresh()->getRawOriginal('password'));
    }

    public function test_local_and_test_environments_still_receive_the_demo_accounts(): void
    {
        foreach (['local', 'testing'] as $environment) {
            User::query()->delete();

            $this->app['env'] = $environment;
            config(['app.seed_demo_users' => true]);

            $this->seedForced(DatabaseSeeder::class);

            $this->assertSame(
                4,
                User::query()->whereIn('email', self::DEMO_EMAILS)->count(),
                "Demo accounts must still be seeded in the {$environment} environment."
            );
        }
    }

    public function test_demo_accounts_stay_off_by_default_everywhere(): void
    {
        /* config/app.php: unset means off. */
        $this->assertStringContainsString(
            "'seed_demo_users' => (bool) env('SEED_DEMO_USERS', false)",
            (string) file_get_contents(base_path('config/app.php'))
        );
        $this->assertMatchesRegularExpression('/^SEED_DEMO_USERS=false\r?$/m', (string) file_get_contents(base_path('.env.docker.example')));

        $this->app['env'] = 'local';
        config(['app.seed_demo_users' => false]);

        $this->seedForced(DatabaseSeeder::class);

        $this->assertSame(0, User::query()->count());
    }
}
