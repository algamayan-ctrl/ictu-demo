<?php

namespace App\Support;

use App\Models\RequestVersion;
use App\Services\DocumentAffiliationService;

final class ReportAffiliation
{
    public static function division(?RequestVersion $version): string
    {
        if (! $version) {
            return '';
        }
        $saved = app(DocumentAffiliationService::class)->resolve($version);

        return $saved['is_external'] ? 'External Borrower' : ($saved['division_code'] ? OrganizationalStructure::label($saved['division_code']) : '');
    }

    public static function display(?RequestVersion $version): string
    {
        return $version ? app(DocumentAffiliationService::class)->resolve($version)['display_label'] : '';
    }

    public static function divisionCode(?RequestVersion $version): string
    {
        return $version ? (string) (app(DocumentAffiliationService::class)->resolve($version)['division_code'] ?? '') : '';
    }

    public static function employeeUnit(?RequestVersion $version): string
    {
        return $version ? (string) (app(DocumentAffiliationService::class)->resolve($version)['office_unit'] ?? '') : '';
    }
}
