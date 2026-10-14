<?php

namespace App\Services;

use App\Enums\AccessClassification;
use App\Enums\RegistrationStatus;
use App\Enums\RegistrationType;
use App\Models\BorrowerRegistration;
use App\Models\RequestVersion;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class BorrowerRequestAffiliationService
{
    private function registration(User $user): ?BorrowerRegistration
    {
        // Use current persisted approval/type rather than a cached relationship.
        return $user->borrowerRegistration()->first();
    }

    public function isExternal(User $user): bool
    {
        return $this->registration($user)?->registration_type === RegistrationType::External;
    }

    public function formContext(User $user, ?RequestVersion $version = null): array
    {
        $external = $version?->isExternalAffiliation() || $this->isExternal($user);

        return [
            'isExternalBorrower' => $external,
            'externalCompanyName' => $external
                ? ($version?->isExternalAffiliation() ? $version->external_company_name : $this->registration($user)?->company_name)
                : null,
        ];
    }

    public function snapshot(User $user, ?RequestVersion $version = null): array
    {
        $registration = $this->registration($user);
        $external = $registration?->registration_type === RegistrationType::External;
        if ($version && $version->isExternalAffiliation() !== $external) {
            throw ValidationException::withMessages(['affiliation' => 'The account and saved request have different affiliation types. Contact SAO before editing this request.']);
        }

        if (! $external) {
            return ['affiliation_type' => RegistrationType::Employee->value, 'external_company_name' => null];
        }
        if ($registration->status !== RegistrationStatus::Approved
            || $user->access_classification !== AccessClassification::BorrowerOnly
            || blank($registration->company_name)) {
            throw ValidationException::withMessages(['affiliation' => 'A SAO-approved external registration with company details is required. Contact SAO.']);
        }
        $company = $version ? $version->external_company_name : $registration->company_name;
        if (blank($company)) {
            throw ValidationException::withMessages(['affiliation' => 'The saved external affiliation is missing. Contact SAO; it was not replaced with an internal office.']);
        }

        return ['affiliation_type' => RegistrationType::External->value, 'external_company_name' => $company];
    }
}
