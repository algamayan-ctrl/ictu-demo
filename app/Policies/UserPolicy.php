<?php

namespace App\Policies;

use App\Enums\AccessClassification;
use App\Enums\AccountStatus;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->account_status === AccountStatus::Active
            && $actor->hasVerifiedInstitutionalEmail()
            && $actor->hasApprovedRegistration()
            && in_array($actor->access_classification, [
                AccessClassification::SpmuHead,
                AccessClassification::IctuMaintainer,
            ], true)
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

    /** @return list<AccessClassification> */
    public function assignableClassifications(User $actor): array
    {
        if ($actor->access_classification === AccessClassification::IctuMaintainer) {
            return AccessClassification::assignableCases();
        }

        return $actor->access_classification === AccessClassification::SpmuHead
            ? [AccessClassification::BorrowerOnly, AccessClassification::SpmuOfficer, AccessClassification::SpmuHead]
            : [];
    }
}
