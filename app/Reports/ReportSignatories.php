<?php

namespace App\Reports;

use App\Enums\AccessClassification;
use App\Enums\AccountStatus;
use App\Models\User;

// This is the printed signatory information for manually signed SPMU reports.
final class ReportSignatories
{
    private const ROLES = [
        'prepared_by' => ['label' => 'Prepared by:', 'role' => 'SPMU Action Officer', 'classification' => AccessClassification::SpmuOfficer],
        'noted_by' => ['label' => 'Noted by:', 'role' => 'SPMU Admin', 'classification' => AccessClassification::SpmuHead],
    ];

    public static function resolve(): array
    {
        $signatories = [];

        foreach (self::ROLES as $key => $definition) {
            $accounts = User::query()
                ->where('access_classification', $definition['classification'])
                ->where('account_status', AccountStatus::Active)
                ->limit(2)
                ->get(['id', 'full_name', 'designation']);
            $account = $accounts->count() === 1 ? $accounts->first() : null;

            $signatories[$key] = [
                'user_id' => $account?->id,
                'name' => $account ? trim((string) $account->full_name) : '',
                'designation' => $account ? trim((string) $account->designation) : '',
                'status' => match ($accounts->count()) {
                    0 => 'missing',
                    1 => 'designated',
                    default => 'ambiguous',
                },
            ];
        }

        return $signatories;
    }

    /** @return array<string, array{label:string, name:string, designation:string}> */
    public static function fromMeta(array $meta): array
    {
        $signatories = [];

        foreach (self::ROLES as $key => $definition) {
            $recorded = $meta['signatories'][$key] ?? [];
            $name = trim((string) ($recorded['name'] ?? ''));
            $designation = trim((string) ($recorded['designation'] ?? ''));
            $signatories[$key] = [
                'label' => $definition['label'],
                'name' => $name !== '' ? mb_strtoupper($name) : 'Signatory not designated',
                'designation' => $designation !== '' ? $designation : $definition['role'],
            ];
        }

        return $signatories;
    }
}
