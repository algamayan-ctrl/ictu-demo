<?php

namespace Tests\Feature;

use App\Console\Commands\BootstrapReferenceData;
use App\Http\Controllers\SettingController;
use App\Models\InventoryItem;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * spmu:bootstrap seeds reference data once, on a database nobody has used,
 * and never again: a restart must not recreate edited inventory or reset
 * organizational units and roles.
 */
class BootstrapReferenceDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_database_is_seeded_once_and_marked_complete(): void
    {
        config(['app.seed_demo_users' => false]);

        $this->assertNull($this->marker());

        $this->bootstrap();

        $this->assertSame(26, InventoryItem::query()->count());
        $this->assertDatabaseHas('roles', ['role_code' => 'BORROWER', 'active' => true]);
        $this->assertDatabaseHas('roles', ['role_code' => 'SPMU', 'active' => true]);
        $this->assertDatabaseHas('roles', ['role_code' => 'ICTU', 'active' => true]);
        $this->assertDatabaseHas('system_settings', ['setting_key' => 'max_upload_mb']);
        $this->assertSame(0, User::query()->count());

        $marker = $this->marker();
        $this->assertSame(BootstrapReferenceData::MODE_SEEDED, $marker['mode']);
        $this->assertNotEmpty($marker['completed_at']);
    }

    public function test_a_second_run_changes_nothing_that_staff_have_customized(): void
    {
        config(['app.seed_demo_users' => false]);
        $this->bootstrap();

        /* What administrators can legitimately do afterwards. */
        DB::table('inventory_items')->where('unique_description', 'Sound System')->update(['unique_description' => 'Sound System (Main Hall)', 'total_quantity' => 5]);
        DB::table('inventory_items')->where('unique_description', 'LED Wall')->delete();
        DB::table('organizational_units')->where('unit_code', 'CCS')->update(['unit_name' => 'Renamed College', 'active' => false]);
        DB::table('roles')->where('role_code', 'BORROWER')->update(['role_name' => 'Renamed Role']);
        DB::table('system_settings')->where('setting_key', 'max_upload_mb')->update(['value_json' => '10']);
        DB::table('units_of_measure')->where('unit_code', 'LOT')->delete();

        $snapshot = fn (): array => [
            'items' => DB::table('inventory_items')->orderBy('id')->get(['id', 'unique_description', 'total_quantity'])->map(fn ($row) => (array) $row)->all(),
            'units' => DB::table('organizational_units')->orderBy('id')->get(['id', 'unit_name', 'parent_unit_id', 'active'])->map(fn ($row) => (array) $row)->all(),
            'roles' => DB::table('roles')->orderBy('id')->get(['id', 'role_name', 'active'])->map(fn ($row) => (array) $row)->all(),
            'settings' => DB::table('system_settings')->orderBy('id')->get(['id', 'setting_key', 'value_json'])->map(fn ($row) => (array) $row)->all(),
            'measures' => DB::table('units_of_measure')->orderBy('id')->pluck('unit_code')->all(),
        ];
        $customized = $snapshot();

        $this->bootstrap();
        $this->bootstrap();

        $this->assertSame($customized, $snapshot());
        $this->assertSame(25, InventoryItem::query()->count());
        $this->assertSame(0, InventoryItem::query()->where('unique_description', 'Sound System')->count());
        $this->assertSame(0, InventoryItem::query()->where('unique_description', 'LED Wall')->count());
    }

    public function test_a_database_already_in_use_is_adopted_without_seeding(): void
    {
        /* An installation from before the marker existed: it has an account but was never seeded here. */
        User::factory()->create();

        $itemsBefore = InventoryItem::query()->count();
        $rolesBefore = DB::table('roles')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        $unitsBefore = DB::table('organizational_units')->orderBy('id')->get(['id', 'unit_name', 'active'])->map(fn ($row) => (array) $row)->all();

        $this->bootstrap();

        $this->assertSame($itemsBefore, InventoryItem::query()->count());
        $this->assertSame($rolesBefore, DB::table('roles')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all());
        $this->assertSame($unitsBefore, DB::table('organizational_units')->orderBy('id')->get(['id', 'unit_name', 'active'])->map(fn ($row) => (array) $row)->all());
        $this->assertSame(1, User::query()->count());

        $marker = $this->marker();
        $this->assertSame(BootstrapReferenceData::MODE_ADOPTED, $marker['mode']);
        $this->assertSame(['users: 1'], $marker['evidence']);

        /* And it stays adopted: a later run seeds nothing either. */
        $this->bootstrap();
        $this->assertSame($itemsBefore, InventoryItem::query()->count());
    }

    public function test_reference_data_alone_does_not_count_as_initialized(): void
    {
        /* Roles and units exist straight after migrating; that is not evidence of use. */
        $this->assertGreaterThan(0, DB::table('roles')->count());
        $this->assertGreaterThan(0, DB::table('organizational_units')->count());
        $this->assertSame(0, InventoryItem::query()->count());

        config(['app.seed_demo_users' => false]);
        $this->bootstrap();

        $this->assertSame(26, InventoryItem::query()->count());
        $this->assertSame(BootstrapReferenceData::MODE_SEEDED, $this->marker()['mode']);
    }

    public function test_a_failure_part_way_keeps_nothing_and_the_next_run_recovers(): void
    {
        config(['app.seed_demo_users' => false]);

        $rolesBefore = DB::table('roles')->count();

        /*
         * The seeders create units, roles and inventory, then fail on the
         * first system setting they add. (Seeding runs without model events,
         * so the failure is raised at the query itself.)
         */
        $failing = true;
        $inventorySeeded = false;

        DB::listen(function ($query) use (&$failing, &$inventorySeeded): void {
            if (! $failing || ! str_starts_with(strtolower($query->sql), 'insert')) {
                return;
            }

            if (str_contains($query->sql, 'inventory_items')) {
                $inventorySeeded = true;
            }

            if (str_contains($query->sql, 'system_settings')) {
                throw new RuntimeException('simulated failure during seeding');
            }
        });

        try {
            $exitCode = $this->withoutMockingConsoleOutput()->artisan('spmu:bootstrap');
        } finally {
            $failing = false;
        }

        $this->assertTrue($inventorySeeded, 'The failure must happen after part of the data was written.');

        $this->assertSame(1, $exitCode);
        $this->assertNull($this->marker(), 'A failed bootstrap must not be recorded as complete.');
        $this->assertSame(0, InventoryItem::query()->count(), 'A failed bootstrap must leave no half-seeded inventory.');
        $this->assertSame($rolesBefore, DB::table('roles')->count());

        /* The next start simply runs it again, from a clean state. */
        $this->bootstrap();

        $this->assertSame(26, InventoryItem::query()->count());
        $this->assertSame(BootstrapReferenceData::MODE_SEEDED, $this->marker()['mode']);
    }

    public function test_first_time_local_setup_still_receives_the_demo_accounts(): void
    {
        $this->app['env'] = 'local';
        config(['app.seed_demo_users' => true]);

        $this->bootstrap();

        $this->assertSame(4, User::query()->count());
        $this->assertSame(26, InventoryItem::query()->count());
    }

    public function test_production_bootstrap_never_creates_demo_accounts(): void
    {
        $this->app['env'] = 'production';
        config(['app.seed_demo_users' => true]);

        $this->bootstrap();

        $this->assertSame(0, User::query()->count());
        $this->assertSame(26, InventoryItem::query()->count());
        $this->assertSame(BootstrapReferenceData::MODE_SEEDED, $this->marker()['mode']);
    }

    public function test_the_marker_is_internal_and_never_shown_or_read_as_a_setting(): void
    {
        config(['app.seed_demo_users' => false]);
        $this->bootstrap();

        $this->assertContains(BootstrapReferenceData::MARKER_KEY, SettingController::PROTECTED_DIRECT_SETTING_KEYS);
        $this->assertNotContains(BootstrapReferenceData::MARKER_KEY, SettingController::TECHNICAL_SETTING_KEYS);
        $this->assertNull(SystemSetting::value(BootstrapReferenceData::MARKER_KEY));
    }

    private function bootstrap(): void
    {
        $this->assertSame(0, $this->withoutMockingConsoleOutput()->artisan('spmu:bootstrap'));
    }

    /** @return array<string, mixed>|null */
    private function marker(): ?array
    {
        $value = DB::table('system_settings')->where('setting_key', BootstrapReferenceData::MARKER_KEY)->value('value_json');

        return $value === null ? null : json_decode($value, true);
    }
}
