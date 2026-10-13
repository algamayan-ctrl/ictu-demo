<?php

namespace App\Policies;

use App\Enums\AccessClassification;
use App\Enums\AccountStatus;
use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Models\BorrowerRegistration;
use App\Models\User;

class BorrowerRegistrationPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->access_classification === AccessClassification::SpmuOfficer
            && $actor->account_status === AccountStatus::Active
            && $actor->hasVerifiedInstitutionalEmail()
            && $actor->hasApprovedRegistration()
            && $actor->hasRole(UserRole::Spmu);
    }

    public function update(User $actor, BorrowerRegistration $registration): bool
    {
        return $this->viewAny($actor) && $registration->user->hasVerifiedInstitutionalEmail();
    }

    public function view(User $actor, BorrowerRegistration $registration): bool
    {
        return $this->viewAny($actor) && ($registration->status !== RegistrationStatus::Pending
            || $registration->user->hasVerifiedInstitutionalEmail());
    }
}
