<?php

namespace App\Support;

use App\Enums\ApprovalStage;
use App\Models\RequestVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

// This is the source of recorded submission and review facts for request reports.
final class RequestReviewFacts
{
    public static function latestSubmittedVersions(): Builder
    {
        return RequestVersion::query()->whereNotNull('submitted_at')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('request_versions as later')
                    ->whereColumn('later.request_id', 'request_versions.request_id')
                    ->whereColumn('later.version_no', '>', 'request_versions.version_no')
                    ->whereNotNull('later.submitted_at');
            });
    }

    public static function firstReview(RequestVersion $version): ?Carbon
    {
        $step = $version->approvalSteps
            ->filter(fn ($step) => $step->stage_code === ApprovalStage::Spmu
                && $step->approver_user_id !== null && $step->decided_at !== null
                && in_array($step->decision, ['VERIFIED', 'APPROVED', 'REJECTED', 'RETURNED_FOR_REVISION'], true))
            ->sortBy(fn ($step) => $step->decided_at->getTimestamp())->first();

        return $step ? Carbon::parse($step->decided_at) : null;
    }

    public static function date(mixed $value, bool $time = false): string
    {
        return $value ? Carbon::parse($value)->timezone('Asia/Manila')->format($time ? 'd/m/Y h:i A' : 'd/m/Y') : 'Not recorded';
    }
}
