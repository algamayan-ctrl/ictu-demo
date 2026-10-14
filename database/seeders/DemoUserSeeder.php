<?php

namespace Database\Seeders;

use App\Enums\AccessClassification;
use App\Enums\AccountStatus;
use App\Enums\EmploymentType;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\UserRoleAssignmentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class DemoUserSeeder extends Seeder
{
    public const PASSWORD = 'SPMU-Demo-2026!';

    public function run(UserRoleAssignmentService $roleAssignments): void
    {
        // Production must never create or reactivate demo identities, even
        // when the demo flag is mistakenly enabled during forced seeding.
        if (! config('app.seed_demo_users')) {
            return;
        }

        /*
         * Demo accounts share a password that is published in this file.
         * A production environment never receives them, whatever
         * SEED_DEMO_USERS says: the flag is skipped, loudly, and the rest of
         * the seeding is left to finish.
         */
        if (app()->environment('production')) {
            $message = 'SEED_DEMO_USERS is enabled in a production environment. Demo accounts were NOT created. Remove SEED_DEMO_USERS from this environment.';

            Log::warning($message);
            $this->command?->warn($message);

            return;
        }
        foreach ($this->accounts() as [$classification, $email, $employeeNo, $name, $unitCode, $employment]) {
            $unit = OrganizationalUnit::query()->where('unit_code', $unitCode)->firstOrFail();
            $user = User::query()->firstOrNew(['email' => $email]);
            $newAccount = ! $user->exists;
            $user->fill([
                'organizational_unit_id' => $unit->id,
                'employee_no' => $employeeNo,
                'full_name' => $name,
                'designation' => $classification === AccessClassification::BorrowerOnly ? null : $classification->label(),
                'employment_type' => $employment,
                'mobile_no' => '09170000000',
                'notification_preferences' => ['system' => true, 'email' => true],
                'account_status' => AccountStatus::Active,
                'access_classification' => $classification,
            ]);
            if ($newAccount) {
                $user->email_verified_at = now();
                $user->password = Hash::make(self::PASSWORD);
            }
            $user->save();
            $roleAssignments->synchronize($user, $classification);
        }
    }

    private function accounts(): array
    {
        return [
            // Borrowers must have a selectable leaf assignment; CSPC is the
            // institution root and deliberately has no classification.
            [AccessClassification::BorrowerOnly, 'borrower@spmu.test', 'DEMO-BORROWER', 'Borrower Demo', 'CCS', EmploymentType::Faculty],
            [AccessClassification::SpmuOfficer, 'spmu@spmu.test', 'DEMO-SPMU', 'SPMU Action Officer Demo', 'SPMU', EmploymentType::Staff],
            [AccessClassification::SpmuHead, 'spmu-head@spmu.test', 'DEMO-SPMU-HEAD', 'SPMU Head Demo', 'SPMU', EmploymentType::Employee],
            // Retired classification: kept as a legacy record that opens no portal.
            [AccessClassification::IctuMaintainer, 'ictu@spmu.test', 'DEMO-ICTU', 'ICTU Maintainer Demo', 'ICTU', EmploymentType::Staff],
        ];
    }
}
