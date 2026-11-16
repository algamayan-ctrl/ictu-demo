<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\AcademicPeriod;
use App\Models\BorrowingRequest;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AcademicYearRequestNumbers;
use App\Services\BorrowingRequestNumberService;
use App\Services\RequestNumberTransition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AcademicRequestNumberingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-30 09:00:00', 'Asia/Manila'));
        foreach ([['2026-2027','2026-08-01','2027-07-31'], ['2027-2028','2027-08-01','2028-07-31']] as [$year,$start,$end]) {
            AcademicPeriod::create(['academic_year' => $year, 'term_code' => 'FIRST_SEMESTER', 'term_name' => 'Configured test period',
                'start_date' => $start, 'end_date' => $end, 'status' => 'ACTIVE']);
        }
    }

    private function activate(): void
    {
        SystemSetting::updateOrCreate(['setting_key' => AcademicYearRequestNumbers::SETTING], ['value_json' => true, 'data_type' => 'BOOLEAN', 'group_code' => 'NUMBERING', 'status' => 'ACTIVE']);
    }

    private function header(string $number = 'LEGACY-ONE', bool $draft = true): BorrowingRequest
    {
        return BorrowingRequest::create(['request_no' => $number, 'borrower_user_id' => User::factory()->create()->id,
            'current_version_no' => 1, 'status' => $draft ? RequestStatus::Draft : RequestStatus::UnderSpmu]);
    }

    private function version(BorrowingRequest $request, string $submitted, int $number = 1): void
    {
        $request->versions()->create(['version_no' => $number, 'submitted_at' => $submitted, 'purpose_event' => 'Numbering fixture',
            'location' => 'CSPC', 'needed_from' => '2026-12-10 08:00:00', 'return_due_at' => '2026-12-11 17:00:00']);
    }

    public function test_drafts_consume_no_sequence_and_first_submission_keeps_number_across_months_years_and_corrections(): void
    {
        $this->activate();
        $borrower = User::factory()->create();
        $draft = app(BorrowingRequestNumberService::class)->createHeader(['borrower_user_id' => $borrower->id, 'status' => RequestStatus::Draft, 'current_version_no' => 1]);
        $this->assertSame('Control No. pending submission', $draft->request_no);
        $this->assertDatabaseCount('academic_request_number_counters', 0);
        $this->assertDatabaseCount('request_number_assignments', 0);
        $service = app(AcademicYearRequestNumbers::class);
        $this->assertSame('BR-2026-11-1', $service->assign($draft, now()));
        $this->version($draft, now()->toDateTimeString());
        $this->travelTo(Carbon::parse('2026-12-01 09:00:00', 'Asia/Manila'));
        $second = $this->header('PENDING-TWO');
        $this->assertSame('BR-2026-12-2', $service->assign($second, now()));
        $this->travelTo(Carbon::parse('2027-01-10 09:00:00', 'Asia/Manila'));
        $third = $this->header('PENDING-THREE');
        $this->assertSame('BR-2027-01-3', $service->assign($third, now()));
        $this->assertSame('BR-2026-11-1', $service->assign($draft->fresh(), now()));
        $this->travelTo(Carbon::parse('2027-08-01 09:00:00', 'Asia/Manila'));
        $nextYear = $this->header('PENDING-NEW-YEAR');
        $this->assertSame('BR-2027-08-1', $service->assign($nextYear, now()));
        $this->assertDatabaseCount('request_number_assignments', 4);
    }

    public function test_failed_submission_transaction_rolls_back_counter_and_assignment(): void
    {
        $this->activate();
        $request = $this->header('PENDING-ROLLBACK');
        try {
            DB::transaction(function () use ($request): void {
                app(AcademicYearRequestNumbers::class)->assign($request, now());
                throw new \RuntimeException('Later submission validation failed.');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('Later submission validation failed.', $exception->getMessage());
        }
        $this->assertDatabaseCount('request_number_assignments', 0);
        $this->assertDatabaseCount('academic_request_number_counters', 0);
        $this->assertSame('PENDING-ROLLBACK', $request->fresh()->getRawOriginal('request_no'));
        $this->assertSame('BR-2026-11-1', app(AcademicYearRequestNumbers::class)->assign($request->fresh(), now()));
    }

    public function test_preview_orders_original_submissions_preserves_aliases_and_can_reverse_an_unchanged_batch(): void
    {
        $later = $this->header('BR-OLD-LATER', false); $this->version($later, '2026-11-08 08:00:00');
        $earlier = $this->header('BR-OLD-EARLIER', false); $this->version($earlier, '2026-10-01 08:00:00');
        $this->version($earlier, '2026-11-09 08:00:00', 2);
        $draft = $this->header('BR-OLD-DRAFT');
        $transition = app(RequestNumberTransition::class);
        $preview = $transition->preview();
        $this->assertSame($preview, $transition->preview());
        $this->assertSame([], $preview['errors']);
        $this->assertSame('BR-2026-10-1', collect($preview['rows'])->firstWhere('request_id', $earlier->id)['new_number']);
        $this->assertSame('BR-2026-11-2', collect($preview['rows'])->firstWhere('request_id', $later->id)['new_number']);
        $this->assertSame('BR-OLD-EARLIER', $earlier->fresh()->request_no, 'Preview must be read-only');
        $batch = $transition->apply($preview['fingerprint']);
        $this->assertTrue(app(AcademicYearRequestNumbers::class)->enabled());
        $this->assertSame('BR-2026-10-1', $earlier->fresh()->request_no);
        $this->assertSame(['BR-OLD-EARLIER'], $earlier->fresh()->previousControlNumbers());
        $this->assertSame('Control No. pending submission', $draft->fresh()->request_no);
        $this->assertDatabaseCount('request_number_assignments', 2);
        $transition->rollback($batch);
        $this->assertSame('BR-OLD-EARLIER', $earlier->fresh()->request_no);
        $this->assertSame('BR-OLD-DRAFT', $draft->fresh()->request_no);
        $this->assertFalse(app(AcademicYearRequestNumbers::class)->enabled());
        $this->assertDatabaseCount('request_number_aliases', 0);
    }

    public function test_unverifiable_history_and_changed_preview_cannot_be_applied(): void
    {
        $record = $this->header('BR-UNDATED', false);
        $transition = app(RequestNumberTransition::class);
        $preview = $transition->preview();
        $this->assertCount(1, $preview['errors']);
        try { $transition->apply($preview['fingerprint']); $this->fail('An unresolved map must fail.'); }
        catch (ValidationException) { $this->assertSame('BR-UNDATED', $record->fresh()->request_no); }
        $this->version($record, '2026-11-01 09:00:00');
        $reviewed = $transition->preview(); $this->header('NEW-DRAFT');
        try { $transition->apply($reviewed['fingerprint']); $this->fail('A changed map must fail.'); }
        catch (ValidationException) { $this->assertDatabaseCount('request_number_assignments', 0); }
    }

    public function test_overlapping_or_missing_academic_years_block_assignment_without_guessing_dates(): void
    {
        $this->activate();
        $request = $this->header('PENDING-NO-YEAR');
        AcademicPeriod::query()->delete();
        try { app(AcademicYearRequestNumbers::class)->assign($request, now()); $this->fail('Missing calendar must fail.'); }
        catch (ValidationException $exception) { $this->assertArrayHasKey('academic_year', $exception->errors()); }
        $this->assertDatabaseCount('request_number_assignments', 0);
        $this->assertDatabaseCount('academic_request_number_counters', 0);
    }

    public function test_two_overlapping_academic_years_require_configuration_review(): void
    {
        $this->activate();
        AcademicPeriod::create(['academic_year' => 'OTHER-YEAR', 'term_code' => 'FIRST_SEMESTER', 'term_name' => 'Overlapping year',
            'start_date' => '2026-10-01', 'end_date' => '2027-02-01', 'status' => 'ACTIVE']);
        $this->expectException(ValidationException::class);
        app(AcademicYearRequestNumbers::class)->assign($this->header('PENDING-AMBIGUOUS'), now());
    }

    public function test_rollback_refuses_to_discard_new_drafts_created_after_activation(): void
    {
        $transition = app(RequestNumberTransition::class);
        $batch = $transition->apply($transition->preview()['fingerprint']);
        $this->header('PENDING-NEW');
        $this->expectException(ValidationException::class);
        $transition->rollback($batch);
    }

    public function test_schema_rollback_requires_reversing_an_applied_draft_only_batch(): void
    {
        $this->header('LEGACY-DRAFT-ONLY');
        $transition = app(RequestNumberTransition::class);
        $transition->apply($transition->preview()['fingerprint']);
        $this->assertDatabaseCount('request_number_assignments', 0);
        $migration = require database_path('migrations/2026_11_14_000001_create_academic_request_numbering_tables.php');
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    public function test_conflicting_historical_reference_blocks_allocation_without_consuming_sequence(): void
    {
        $this->activate();
        $this->header('BR-2026-11-1', false);
        $draft = $this->header('PENDING-COLLISION');
        try { app(AcademicYearRequestNumbers::class)->assign($draft, now()); $this->fail('A recorded identifier must not be reused.'); }
        catch (ValidationException $exception) { $this->assertArrayHasKey('request_no', $exception->errors()); }
        $this->assertDatabaseCount('request_number_assignments', 0);
        $this->assertDatabaseCount('academic_request_number_counters', 0);
        $this->assertSame('PENDING-COLLISION', $draft->fresh()->getRawOriginal('request_no'));
    }
}
