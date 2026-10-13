<?php

namespace App\Console\Commands;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * One-time reference-data bootstrap.
 *
 * The seeders are written for an empty database. Run again after staff have
 * edited the data, they recreate renamed or deleted opening inventory items
 * and reset organizational units and roles. This command is the only thing
 * the container entrypoint runs, and it seeds at most once per database:
 *
 *  1. A completion marker exists: nothing is done.
 *  2. No marker, but the database is already in use (it has accounts,
 *     borrowing requests or audit history): nothing is seeded. The marker is
 *     recorded so the question is never asked again.
 *  3. No marker and no sign of use: the seeders run inside one transaction
 *     together with the marker. A failure rolls all of it back, so the next
 *     start begins again from a clean state instead of a half-seeded one.
 *
 * The marker lives in the database it describes (system_settings), not in a
 * file, so it travels with a backup and cannot disagree with the data.
 */
class BootstrapReferenceData extends Command
{
    /** Hidden from the settings screens; see SettingController. */
    public const MARKER_KEY = 'reference_data_bootstrap';

    public const MODE_SEEDED = 'SEEDED';

    public const MODE_ADOPTED = 'ADOPTED_EXISTING_DATA';

    /** Tables whose rows only exist once people have used the system. */
    private const USAGE_TABLES = ['users', 'borrowing_requests', 'audit_events'];

    protected $signature = 'spmu:bootstrap';

    protected $description = 'Seed reference data once on a new database; never reseeds a database that is initialized or in use';

    public function handle(): int
    {
        if (! Schema::hasTable('system_settings')) {
            $this->error('The database has not been migrated. Run "php artisan migrate --force" first.');

            return self::FAILURE;
        }

        $marker = $this->marker();

        if ($marker !== null) {
            $this->info('Reference data was already initialized ('.($marker['mode'] ?? 'unknown').', '.($marker['completed_at'] ?? 'unknown time').'). Nothing was seeded.');

            return self::SUCCESS;
        }

        $inUse = $this->usage();

        if ($inUse !== []) {
            $this->writeMarker(self::MODE_ADOPTED, $inUse);

            $message = 'This database is already in use ('.implode(', ', $inUse).'). Reference data was NOT seeded; the existing data is kept as it is.';
            Log::info($message);
            $this->warn($message);

            return self::SUCCESS;
        }

        try {
            DB::transaction(function (): void {
                $exitCode = $this->call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

                if ($exitCode !== self::SUCCESS) {
                    throw new RuntimeException("db:seed exited with code {$exitCode}.");
                }

                $this->writeMarker(self::MODE_SEEDED, []);
            });
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Reference data could not be seeded. Nothing was kept, so it is safe to run this command again. Cause: '.$exception::class);

            return self::FAILURE;
        }

        $this->info('Reference data seeded. This database will not be seeded again.');

        return self::SUCCESS;
    }

    /** @return array<string, mixed>|null */
    private function marker(): ?array
    {
        $value = DB::table('system_settings')->where('setting_key', self::MARKER_KEY)->value('value_json');

        if ($value === null) {
            return null;
        }

        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * What shows the database is already in use, as "table (rows)".
     *
     * @return list<string>
     */
    private function usage(): array
    {
        $found = [];

        foreach (self::USAGE_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $rows = DB::table($table)->count();

            if ($rows > 0) {
                $found[] = "{$table}: {$rows}";
            }
        }

        return $found;
    }

    /** @param  list<string>  $evidence */
    private function writeMarker(string $mode, array $evidence): void
    {
        $now = now();

        DB::table('system_settings')->insert([
            'setting_key' => self::MARKER_KEY,
            'value_json' => json_encode([
                'mode' => $mode,
                'completed_at' => $now->toIso8601String(),
                'evidence' => $evidence,
            ]),
            'data_type' => 'JSON',
            'group_code' => 'SYSTEM',
            'description' => 'Internal. Records that reference data was initialized once. Do not edit or delete: without it the next container start may seed reference data again.',
            /* Not ACTIVE: SystemSetting::value() never returns it as a configuration value. */
            'status' => 'INTERNAL',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
