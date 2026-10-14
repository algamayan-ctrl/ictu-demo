<?php

namespace App\Policies;

use App\Enums\AccessClassification;
use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    /** Account administration belongs to the active, verified SPMU Admin/Head. */
    public function viewAny(User $actor): bool
    {
        return $actor->account_status === AccountStatus::Active
            && $actor->hasVerifiedInstitutionalEmail()
            && $actor->hasApprovedRegistration()
            && $actor->access_classification === AccessClassification::SpmuHead
            && $actor->hasRole($actor->access_classification->primaryWorkspace());
    }

    public function create(User $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function update(User $actor, User $target): bool
    {
        return $this->viewAny($actor)
            && in_array($target->access_classification, $this->assignableClassifications($actor), true);
    }

    public function deactivate(User $actor, User $target): bool
    {
        return $this->update($actor, $target)
            && (int) $actor->getKey() !== (int) $target->getKey();
    }

    public function reactivate(User $actor, User $target): bool
    {
        return $this->deactivate($actor, $target);
    }

    public function deletePendingRegistration(User $actor, User $target): bool
    {
        return $this->update($actor, $target);
    }

    /**
     * The Head may resend for any account they administer. The Action
     * Officer, who reviews borrower registrations, may resend only for a
     * borrower account.
     */
    public function resendEmailVerification(User $actor, User $target): bool
    {
        if ($this->update($actor, $target)) {
            return true;
        }

        return $actor->account_status === AccountStatus::Active
            && $actor->hasVerifiedInstitutionalEmail()
            && $actor->hasApprovedRegistration()
            && $actor->access_classification === AccessClassification::SpmuOfficer
            && $actor->hasRole(UserRole::Spmu)
            && $target->access_classification === AccessClassification::BorrowerOnly;
    }

    /** @return list<AccessClassification> */
    public function assignableClassifications(User $actor): array
    {
        return $actor->access_classification === AccessClassification::SpmuHead
            ? [AccessClassification::BorrowerOnly, AccessClassification::SpmuOfficer, AccessClassification::SpmuHead]
            : [];
    }
}
