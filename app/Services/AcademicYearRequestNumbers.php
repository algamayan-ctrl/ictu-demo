<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\BorrowingRequest;
use App\Models\SystemSetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// This is the Control No. allocation for first submissions within an academic year.
class AcademicYearRequestNumbers
{
    public const SETTING = 'academic_request_numbering_active';

    public function enabled(): bool
    {
        return Schema::hasTable('request_number_assignments')
            && SystemSetting::value(self::SETTING, false) === true;
    }

    public function pendingNumber(): string
    {
        return 'PENDING-'.Str::uuid();
    }

    public function academicYear(CarbonInterface $time): string
    {
        $date = $time->copy()->timezone('Asia/Manila')->toDateString();
        $matches = AcademicPeriod::query()->get()->groupBy('academic_year')
            ->filter(fn ($periods) => $date >= $periods->min(fn ($period) => $period->start_date->toDateString())
                && $date <= $periods->max(fn ($period) => $period->end_date->toDateString()));
        if ($matches->count() !== 1) {
            throw ValidationException::withMessages(['academic_year' => 'SPMU must configure one unambiguous academic year covering the submission date before a Control No. can be assigned.']);
        }

        return (string) $matches->keys()->first();
    }

    public function assign(BorrowingRequest $request, CarbonInterface $time): string
    {
        return DB::transaction(function () use ($request, $time): string {
            $this->lockSubmission($request);
            $request = BorrowingRequest::query()->lockForUpdate()->findOrFail($request->id);
            $existing = DB::table('request_number_assignments')->where('request_id', $request->id)->first();
            if ($existing) return $existing->number;
            if ($request->versions()->whereNotNull('submitted_at')->exists()) {
                throw ValidationException::withMessages(['request_no' => 'This historical request needs a verified Control No. mapping before it can be resubmitted.']);
            }
            $year = $this->academicYear($time);
            DB::table('academic_request_number_counters')->insertOrIgnore(['academic_year' => $year, 'last_number' => 0]);
            $counter = DB::table('academic_request_number_counters')->where('academic_year', $year)->lockForUpdate()->first();
            $sequence = (int) $counter->last_number + 1;
            $number = 'BR-'.$time->copy()->timezone('Asia/Manila')->format('Y-m').'-'.$sequence;
            if (DB::table('borrowing_requests')->where('request_no', $number)->where('id', '!=', $request->id)->exists()
                || DB::table('request_number_aliases')->where('alias', $number)->where('request_id', '!=', $request->id)->exists()) {
                throw ValidationException::withMessages(['request_no' => 'The next academic-year Control No. conflicts with a recorded reference. SPMU must review the numbering map before submission.']);
            }
            $old = (string) $request->getRawOriginal('request_no');
            if ($old !== '' && ! str_starts_with($old, 'PENDING-') && $old !== $number) {
                DB::table('request_number_aliases')->insert(['request_id' => $request->id, 'alias' => $old, 'created_at' => now()]);
            }
            DB::table('academic_request_number_counters')->where('academic_year', $year)->update(['last_number' => $sequence]);
            DB::table('request_number_assignments')->insert(['request_id' => $request->id, 'academic_year' => $year,
                'sequence_number' => $sequence, 'number' => $number, 'assigned_at' => $time]);
            $request->update(['request_no' => $number]);

            return $number;
        }, 5);
    }

    public function lockSubmission(BorrowingRequest $request): void
    {
        // This is the SQLite write lock before reading a first-submission counter.
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::table('borrowing_requests')->where('id', $request->id)->update(['request_no' => DB::raw('request_no')]);
        }
    }

    public function previousNumbers(BorrowingRequest $request): array
    {
        if (! Schema::hasTable('request_number_aliases')) return [];
        return DB::table('request_number_aliases')->where('request_id', $request->id)->orderBy('id')->pluck('alias')->all();
    }
}
