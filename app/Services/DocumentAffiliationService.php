<?php

namespace App\Services;

use App\Enums\RegistrationType;
use App\Models\RequestVersion;
use Illuminate\Validation\ValidationException;

class DocumentAffiliationService
{
    /**
     * Resolve saved version facts only. Agency names are never CSPC unit facts.
     * A later user/registration edit must not change a reviewed affiliation.
     *
     * @return array{is_external: bool, employee_checked: bool, others_checked: bool, company_name: ?string, others_label: string, display_label: string, division_code: ?string, office_unit: ?string}
     */
    public function resolve(RequestVersion $version): array
    {
        $attributes = $version->getAttributes();
        $type = $attributes['affiliation_type'] ?? null;

        if ($type === RegistrationType::External->value) {
            $company = trim((string) ($attributes['external_company_name'] ?? ''));
            $label = $company !== '' ? $company : 'Affiliation not recorded';

            return [
                'is_external' => true,
                'employee_checked' => false,
                'others_checked' => true,
                'company_name' => $company !== '' ? $company : null,
                'others_label' => 'External Borrower / '.$label,
                'display_label' => $company !== '' ? $company : 'External Borrower / '.$label,
                'division_code' => null,
                'office_unit' => null,
            ];
        }

        if ($type !== null && $type !== RegistrationType::Employee->value) {
            throw ValidationException::withMessages([
                'affiliation' => 'The saved request affiliation is not recognized. Review the request before generating a controlled document.',
            ]);
        }

        // Legacy versions predate affiliation_type; retain their active Employee layout.
        // Faculty/staff also retain the existing active form's Employee checkbox.
        return [
            'is_external' => false,
            'employee_checked' => true,
            'others_checked' => false,
            'company_name' => null,
            'others_label' => '',
            'display_label' => (string) ($attributes['office_unit'] ?? ''),
            'division_code' => $attributes['division_code'] ?? null,
            'office_unit' => $attributes['office_unit'] ?? null,
        ];
    }
}
