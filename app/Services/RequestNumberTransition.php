<?php

namespace App\Services;

use App\Models\BorrowingRequest;
use App\Models\SystemSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// This is the preview and reversible mapping for historical Control No. changes.
class RequestNumberTransition
{
    public function preview(): array
    {
        $requests = BorrowingRequest::query()->withMin('versions as first_submission', 'submitted_at')->get()
            ->sortBy(fn ($request) => [(string) ($request->first_submission ?? '9999'), $request->id]);
        $sequences = []; $rows = []; $errors = []; $numbers = [];
        $oldOwners = $requests->mapWithKeys(fn ($request) => [(string) $request->getRawOriginal('request_no') => $request->id]);
        foreach ($requests as $request) {
            $old = (string) $request->getRawOriginal('request_no');
            $year = null; $sequence = null; $submitted = null;
            if ($request->first_submission) {
                $submitted = Carbon::parse($request->first_submission)->timezone('Asia/Manila');
                try {
                    $year = app(AcademicYearRequestNumbers::class)->academicYear($submitted);
                } catch (ValidationException $exception) {
                    $errors[] = ['request_id' => $request->id, 'reason' => 'Submission date has no unambiguous configured academic year.'];
                    continue;
                }
                $sequence = $sequences[$year] = ($sequences[$year] ?? 0) + 1;
                $new = 'BR-'.$submitted->format('Y-m').'-'.$sequence;
            } elseif ($request->status->value === 'DRAFT') {
                $new = str_starts_with($old, 'PENDING-') ? $old : 'PENDING-REQUEST-'.$request->id;
            } else {
                $errors[] = ['request_id' => $request->id, 'reason' => 'A non-draft record has no verifiable first-submission timestamp.'];
                continue;
            }
            if (isset($numbers[$new]) || (isset($oldOwners[$new]) && (int) $oldOwners[$new] !== $request->id)) {
                $errors[] = ['request_id' => $request->id, 'reason' => 'New number conflicts with another current or historical request reference.'];
            }
            if (Schema::hasTable('request_number_aliases') && DB::table('request_number_aliases')->where('alias', $new)->where('request_id', '!=', $request->id)->exists()) {
                $errors[] = ['request_id' => $request->id, 'reason' => 'New number conflicts with a saved historical alias.'];
            }
            $numbers[$new] = $request->id;
            $rows[] = ['request_id' => $request->id, 'old_number' => $old, 'new_number' => $new,
                'academic_year' => $year, 'sequence_number' => $sequence, 'first_submitted_at' => $submitted?->toDateTimeString()];
        }
        $calendar = \App\Models\AcademicPeriod::query()->orderBy('id')->get(['id','academic_year','start_date','end_date'])->toArray();
        $activationYear = null;
        try { $activationYear = app(AcademicYearRequestNumbers::class)->academicYear(now('Asia/Manila')); }
        catch (ValidationException) { }
        $result = ['rows' => $rows, 'errors' => $errors, 'year_totals' => $sequences, 'calendar' => $calendar,
            'activation_year' => $activationYear, 'activation_ready' => $activationYear !== null];
        $result['fingerprint'] = hash('sha256', json_encode($result, JSON_THROW_ON_ERROR));

        return $result;
    }

    public function apply(string $fingerprint): string
    {
        if (! Schema::hasTable('request_number_changes')) {
            throw ValidationException::withMessages(['numbering' => 'Install the additive numbering schema before applying a reviewed map.']);
        }
        return DB::transaction(function () use ($fingerprint): string {
            BorrowingRequest::query()->orderBy('id')->lockForUpdate()->get(['id']);
            if (app(AcademicYearRequestNumbers::class)->enabled() || DB::table('request_number_assignments')->exists()) {
                throw ValidationException::withMessages(['numbering' => 'Academic-year numbering is already active or has recorded assignments.']);
            }
            $preview = $this->preview();
            if ($fingerprint === '' || ! hash_equals($preview['fingerprint'], $fingerprint) || $preview['errors']) {
                throw ValidationException::withMessages(['numbering' => 'The reviewed mapping changed or contains unresolved records. Generate and review a fresh preview.']);
            }
            app(AcademicYearRequestNumbers::class)->academicYear(now('Asia/Manila'));
            $batch = (string) Str::uuid();
            DB::table('request_number_batches')->insert(['id' => $batch, 'status' => 'APPLIED',
                'counter_snapshot' => json_encode(DB::table('academic_request_number_counters')->get()), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($preview['rows'] as $row) {
                DB::table('request_number_changes')->insert(['batch_id' => $batch, 'request_id' => $row['request_id'],
                    'old_number' => $row['old_number'], 'new_number' => $row['new_number']]);
                DB::table('borrowing_requests')->where('id', $row['request_id'])->update(['request_no' => 'MAPPING-'.Str::uuid()]);
            }
            foreach ($preview['rows'] as $row) {
                DB::table('borrowing_requests')->where('id', $row['request_id'])->update(['request_no' => $row['new_number']]);
                if ($row['old_number'] !== $row['new_number'] && !str_starts_with($row['old_number'], 'PENDING-')) {
                    DB::table('request_number_aliases')->insert(['request_id' => $row['request_id'], 'alias' => $row['old_number'], 'migration_batch' => $batch, 'created_at' => now()]);
                }
                if ($row['academic_year'] !== null) {
                    DB::table('request_number_assignments')->insert(['request_id' => $row['request_id'], 'academic_year' => $row['academic_year'],
                        'sequence_number' => $row['sequence_number'], 'number' => $row['new_number'], 'assigned_at' => $row['first_submitted_at'], 'migration_batch' => $batch]);
                }
            }
            foreach ($preview['year_totals'] as $year => $total) {
                DB::table('academic_request_number_counters')->updateOrInsert(['academic_year' => $year], ['last_number' => $total]);
            }
            SystemSetting::updateOrCreate(['setting_key' => AcademicYearRequestNumbers::SETTING], [
                'value_json' => true, 'data_type' => 'BOOLEAN', 'group_code' => 'NUMBERING', 'status' => 'ACTIVE',
                'description' => 'First-submission academic-year numbering activated after a verified historical mapping.',
            ]);
            app(AuditService::class)->record('REQUEST_CONTROL_NUMBERS_MAPPED', \App\Models\AuditEvent::class,
                reason: 'Verified first-submission academic-year mapping applied.', after: ['batch_id' => $batch, 'records' => count($preview['rows'])]);

            return $batch;
        }, 5);
    }

    public function rollback(string $batch): void
    {
        DB::transaction(function () use ($batch): void {
            $journal = DB::table('request_number_batches')->where('id', $batch)->lockForUpdate()->first();
            if (! $journal || $journal->status !== 'APPLIED') throw ValidationException::withMessages(['numbering' => 'An applied numbering batch is required.']);
            if (DB::table('request_number_assignments')->whereNull('migration_batch')->orWhere('migration_batch', '!=', $batch)->exists()) {
                throw ValidationException::withMessages(['numbering' => 'New submissions or other numbering batches exist. Review them before reversing the historical map.']);
            }
            $changes = DB::table('request_number_changes')->where('batch_id', $batch)->orderBy('request_id')->get();
            if (DB::table('borrowing_requests')->whereNotIn('id', $changes->pluck('request_id'))->exists()) {
                throw ValidationException::withMessages(['numbering' => 'New request headers exist after activation. Review them before reversing this batch.']);
            }
            foreach ($changes as $change) {
                $row = DB::table('borrowing_requests')->where('id', $change->request_id)->lockForUpdate()->first();
                if (! $row || $row->request_no !== $change->new_number) throw ValidationException::withMessages(['numbering' => 'A mapped request changed since activation; reversal was cancelled.']);
            }
            foreach ($changes as $change) DB::table('borrowing_requests')->where('id', $change->request_id)->update(['request_no' => 'REVERSING-'.Str::uuid()]);
            foreach ($changes as $change) DB::table('borrowing_requests')->where('id', $change->request_id)->update(['request_no' => $change->old_number]);
            DB::table('request_number_assignments')->where('migration_batch', $batch)->delete();
            DB::table('request_number_aliases')->where('migration_batch', $batch)->delete();
            DB::table('academic_request_number_counters')->delete();
            $snapshot = json_decode($journal->counter_snapshot, true);
            if ($snapshot) DB::table('academic_request_number_counters')->insert($snapshot);
            SystemSetting::where('setting_key', AcademicYearRequestNumbers::SETTING)->update(['value_json' => json_encode(false)]);
            DB::table('request_number_batches')->where('id', $batch)->update(['status' => 'REVERSED', 'updated_at' => now()]);
            app(AuditService::class)->record('REQUEST_CONTROL_NUMBERS_REVERSED', \App\Models\AuditEvent::class,
                reason: 'Unchanged historical Control No. mapping reversed.', after: ['batch_id' => $batch]);
        }, 5);
    }
}
