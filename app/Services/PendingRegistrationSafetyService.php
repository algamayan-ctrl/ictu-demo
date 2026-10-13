<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PendingRegistrationSafetyService
{
    /** Registration metadata may be removed with an unused account. */
    private const REGISTRATION_TABLES = [
        'user_roles', 'user_organizational_units', 'email_verification_otps', 'sessions',
    ];

    public function hasLinkedRecords(int $userId): bool
    {
        $schema = DB::connection()->getSchemaBuilder();

        // Read the actual schema so newly added user-linked business tables
        // receive protection without maintaining a second list of modules.
        foreach ($schema->getTableListing(null, false) as $table) {
            if (in_array($table, self::REGISTRATION_TABLES, true)) {
                continue;
            }

            foreach ($schema->getForeignKeys($table) as $foreignKey) {
                if ($foreignKey['foreign_table'] !== 'users') {
                    continue;
                }

                foreach ($foreignKey['columns'] as $index => $column) {
                    if (($foreignKey['foreign_columns'][$index] ?? null) === 'id'
                        && DB::table($table)->where($column, $userId)->exists()) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
