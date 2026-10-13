<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\InventoryItem;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductionDemoSeedingTest extends TestCase
{
    use RefreshDatabase;

    public static function productionFlags(): array
    {
        return ['enabled' => [true], 'disabled' => [false]];
    }

    #[DataProvider('productionFlags')]
    public function test_production_never_creates_demo_accounts(bool $enabled): void
    {
        config(['app.seed_demo_users' => $enabled]);

        $this->inEnvironment('production', fn () => $this->seedForced(DatabaseSeeder::class));

        $this->assertDatabaseCount('users', 0);
    }

    public function test_production_preserves_existing_accounts_and_roles(): void
    {
        config(['app.seed_demo_users' => true]);
        $this->seed(DatabaseSeeder::class);
        $user = User::query()->where('email', 'borrower@spmu.test')->firstOrFail();
        $user->update([
            'full_name' => 'Retained Account Owner',
            'account_status' => AccountStatus::Inactive,
            'password' => 'Different-password-2026!',
        ]);
        $before = $user->fresh()->getAttributes();
        $rolesBefore = $user->roles()->get()->toArray();
        $countBefore = User::query()->count();

        $this->inEnvironment('production', fn () => $this->seedForced(DemoUserSeeder::class));

        $this->assertSame($countBefore, User::query()->count());
        $this->assertSame($before, $user->fresh()->getAttributes());
        $this->assertSame($rolesBefore, $user->roles()->get()->toArray());
    }

    public static function demoEnvironments(): array
    {
        return ['local' => ['local'], 'testing' => ['testing']];
    }

    #[DataProvider('demoEnvironments')]
    public function test_explicitly_enabled_nonproduction_demos_still_work(string $environment): void
    {
        config(['app.seed_demo_users' => true]);

        $this->inEnvironment($environment, fn () => $this->seed(DatabaseSeeder::class));

        $this->assertDatabaseCount('users', 4);
        $this->assertDatabaseHas('users', ['email' => 'borrower@spmu.test']);
        $this->assertDatabaseHas('users', ['email' => 'spmu@spmu.test']);
        $this->assertDatabaseHas('users', ['email' => 'spmu-head@spmu.test']);
        $this->assertDatabaseHas('users', ['email' => 'ictu@spmu.test']);
    }

    public function test_disabled_local_demos_do_not_create_accounts(): void
    {
        config(['app.seed_demo_users' => false]);

        $this->inEnvironment('local', fn () => $this->seed(DatabaseSeeder::class));

        $this->assertDatabaseCount('users', 0);
    }

    public function test_repeated_demo_seeding_keeps_identity_and_password(): void
    {
        config(['app.seed_demo_users' => true]);
        $this->seed(DatabaseSeeder::class);
        $identitiesBefore = User::query()->orderBy('id')->get(['id', 'email', 'password'])->toArray();

        $this->seed(DemoUserSeeder::class);

        $this->assertSame($identitiesBefore, User::query()->orderBy('id')->get(['id', 'email', 'password'])->toArray());
    }

    public function test_production_reference_seeding_is_not_blocked(): void
    {
        config(['app.seed_demo_users' => true]);

        $this->inEnvironment('production', fn () => $this->seedForced(DatabaseSeeder::class));

        $this->assertDatabaseCount('users', 0);
        $this->assertGreaterThan(0, OrganizationalUnit::query()->count());
        $this->assertGreaterThan(0, Role::query()->count());
        $this->assertGreaterThan(0, InventoryItem::query()->count());
    }

    private function seedForced(string $seeder): void
    {
        $this->artisan('db:seed', ['--class' => $seeder, '--force' => true])->assertExitCode(0);
    }

    private function inEnvironment(string $environment, callable $operation): void
    {
        $previous = app()->environment();
        app()->instance('env', $environment);

        try {
            $operation();
        } finally {
            app()->instance('env', $previous);
        }
    }
}
