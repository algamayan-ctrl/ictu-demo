<?php

namespace App\Policies;

use App\Enums\AccessClassification;
use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\BorrowingRequest;
use App\Models\User;

class BorrowingRequestPolicy
{
    public function cancel(User $actor, BorrowingRequest $request): bool
    {
        if ($actor->account_status !== AccountStatus::Active
            || ! $actor->hasVerifiedInstitutionalEmail()
            || ! $actor->hasApprovedRegistration()) {
            return false;
        }

        // State validation separately explains why an owner's missed or
        // physically released request cannot be cancelled.
        if ($actor->access_classification === AccessClassification::BorrowerOnly) {
            return (int) $request->borrower_user_id === (int) $actor->id;
        }

        if (! $actor->hasRole(UserRole::Spmu)) {
            return false;
        }

        $request->loadMissing('custody');
        if ($request->custody?->pickup_expired_at && ! $request->custody?->released_at) {
            return $actor->access_classification === AccessClassification::SpmuOfficer;
        }

        return $actor->access_classification === AccessClassification::SpmuHead
            || ($actor->access_classification === AccessClassification::SpmuOfficer
                && $actor->activeDelegationFor('SPMU') !== null);
    }
}
