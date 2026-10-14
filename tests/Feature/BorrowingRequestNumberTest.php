<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Enums\RequestStatus;
use App\Models\BorrowerRegistration;
use App\Models\BorrowingRequest;
use App\Models\InventoryItem;
use App\Models\StoredFile;
use App\Models\User;
use App\Services\AuditService;
use App\Services\BorrowingRequestNumberService;
use App\Services\UserRoleAssignmentService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\EvidenceFixtures;
use Tests\TestCase;

class BorrowingRequestNumberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-13 09:00:00', 'Asia/Manila'));
        config(['app.seed_demo_users' => true]);
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        Storage::fake('local');
    }

    #[DataProvider('createModes')]
    public function test_two_distinct_actual_creates_in_the_same_second_are_saved_without_reservation(bool $external, bool $submit, bool $offCampus): void
    {
        $borrower = $this->borrower($external);
        $this->signature();
        $clock = now()->toIso8601String();
        foreach (['First legitimate event', 'Second legitimate event'] as $purpose) {
            $this->post(route('requests.store'), $this->payload($borrower, $purpose, $submit, $offCampus)
                + ($submit ? $this->letter($purpose) : []))->assertSessionHasNoErrors()->assertRedirect()
                ->assertSessionHas('status', $submit
                    ? 'Request version E-signed and submitted with the required scanned document(s) to SPMU for verification.'
                    : 'Draft saved. You can continue editing and submit when the required scanned document(s) are complete.');
        }
        $this->assertSame($clock, now()->toIso8601String());
        $requests = BorrowingRequest::orderBy('id')->get();
        $this->assertCount(2, $requests);
        $this->assertNotSame($requests[0]->request_no, $requests[1]->request_no);
        foreach ($requests as $index => $request) {
            $this->assertStringStartsWith('BR-'.now()->format('YmdHis').'-'.$borrower->id.'-', $request->request_no);
            $this->assertLessThanOrEqual(50, strlen($request->request_no));
            $this->assertSame($borrower->id, $request->borrower_user_id);
            $this->assertSame(1, $request->current_version_no);
            $this->assertSame($submit ? RequestStatus::UnderSpmu : RequestStatus::Draft, $request->status);
            $this->assertSame($index === 0 ? 'First legitimate event' : 'Second legitimate event', $request->currentVersion->purpose_event);
            $this->assertSame($external ? null : $borrower->organizational_unit_id, $request->accountable_unit_id);
            if ($external) {
                $this->assertSame('Reviewed Request Number Agency', $request->currentVersion->external_company_name);
                $this->assertNull($request->currentVersion->division_code);
                $this->assertNull($request->currentVersion->office_unit);
            }
            if ($submit) {
                $this->assertNotNull($request->currentVersion->borrower_signature_snapshot_id);
                $this->assertSame($offCampus ? 1 : 2, $request->currentVersion->approvalSteps()->sole()->sequence_no);
                $this->assertSame(1, $request->currentVersion->supportingDocuments()->current()->count());
                $recipient = User::where('email', $offCampus ? 'spmu@spmu.test' : 'spmu-head@spmu.test')->sole();
                $this->assertDatabaseHas('notification_deliveries', ['recipient_user_id' => $recipient->id]);
            }
        }
        $this->assertDatabaseCount('request_versions', 2);
        $this->assertDatabaseCount('signature_snapshots', $submit ? 2 : 0);
        $this->assertDatabaseCount('approval_steps', $submit ? 2 : 0);
        $this->assertSame(2, DB::table('audit_events')->where('action_code', $submit ? 'REQUEST_SUBMITTED' : 'REQUEST_DRAFT_CREATED')->count());
        $this->assertNoReservation();
        foreach ($requests as $request) {
            $this->get(route($submit ? 'requests.show' : 'requests.edit', $request))->assertOk();
        }
        $this->get(route('requests.index'))->assertOk()->assertSeeText($requests[0]->request_no)->assertSeeText($requests[1]->request_no);
    }

    public static function createModes(): array
    {
        $cases = [];
        foreach ([false, true] as $external) {
            foreach ([false, true] as $submit) {
                foreach ([false, true] as $offCampus) {
                    $cases[($external ? 'external' : 'employee').' '.($submit ? 'signed' : 'draft').' '.($offCampus ? 'off campus' : 'on campus')] = [$external, $submit, $offCampus];
                }
            }
        }

        return $cases;
    }

    public function test_completed_action_token_still_prevents_a_duplicate_create(): void
    {
        $borrower = $this->borrower(true);
        $payload = $this->payload($borrower, 'One legitimate action') + ['_action_token' => 'req01a-completed-action'];
        $this->post(route('requests.store'), $payload)->assertSessionHasNoErrors();
        $before = $this->state();
        $this->post(route('requests.store'), $payload)->assertRedirect()->assertSessionHas('status', 'This action was already completed. No duplicate transaction was created.');
        $this->assertSame($before, $this->state());
    }

    public function test_forced_number_collision_retries_only_the_header_and_preserves_existing_evidence(): void
    {
        $borrower = $this->borrower(true);
        $this->signature();
        $first = 'BR-'.now()->format('YmdHis').'-'.$borrower->id.'-COLLISION1';
        $second = 'BR-'.now()->format('YmdHis').'-'.$borrower->id.'-RECOVERY01';
        $service = \Mockery::mock(BorrowingRequestNumberService::class)->makePartial();
        $service->shouldReceive('newNumber')->with($borrower->id)->times(3)->andReturn($first, $first, $second);
        $this->app->instance(BorrowingRequestNumberService::class, $service);
        $this->post(route('requests.store'), $this->payload($borrower, 'First signed event', true) + $this->letter('first'))->assertSessionHasNoErrors();
        $old = $this->state();
        $this->post(route('requests.store'), $this->payload($borrower, 'Second signed event', true) + $this->letter('second'))->assertSessionHasNoErrors();
        $this->assertSame([$first, $second], BorrowingRequest::orderBy('id')->pluck('request_no')->all());
        $this->assertOldStatePreserved($old);
        $this->assertDatabaseCount('request_versions', 2);
        $this->assertDatabaseCount('signature_snapshots', 2);
        $this->assertDatabaseCount('request_supporting_documents', 2);
        $this->assertSame(2, DB::table('audit_events')->where('action_code', 'REQUEST_SUBMITTED')->count());
        $this->assertNoReservation();
    }

    public function test_collision_exhaustion_is_bounded_and_writes_no_partial_request(): void
    {
        $borrower = $this->borrower(true);
        $number = 'BR-'.now()->format('YmdHis').'-'.$borrower->id.'-REPEATED01';
        $service = \Mockery::mock(BorrowingRequestNumberService::class)->makePartial();
        $service->shouldReceive('newNumber')->times(6)->andReturn($number);
        $this->app->instance(BorrowingRequestNumberService::class, $service);
        $this->post(route('requests.store'), $this->payload($borrower, 'First draft'))->assertSessionHasNoErrors();
        $old = $this->state();
        $this->withoutExceptionHandling();
        try {
            $this->post(route('requests.store'), $this->payload($borrower, 'Second draft'));
            $this->fail('All five candidate collisions must stop creation.');
        } catch (UniqueConstraintViolationException) {
            $this->assertSame($old, $this->state());
        }
    }

    public function test_foreign_key_failure_is_not_retried_as_a_number_collision(): void
    {
        $borrower = $this->borrower(true);
        $before = $this->state();
        $service = \Mockery::mock(BorrowingRequestNumberService::class)->makePartial();
        $service->shouldReceive('newNumber')->once()->andReturn('BR-FOREIGN-KEY-PROBE');
        try {
            DB::transaction(fn () => $service->createHeader(['borrower_user_id' => 999999999, 'accountable_unit_id' => null, 'current_version_no' => 1, 'status' => RequestStatus::Draft]));
            $this->fail('Foreign key failure must not create a request.');
        } catch (QueryException $exception) {
            $this->assertNotInstanceOf(UniqueConstraintViolationException::class, $exception);
            $this->assertSame($before, $this->state());
        }
    }

    public function test_unrelated_unique_violation_is_not_retried(): void
    {
        $borrower = $this->borrower(true);
        $this->post(route('requests.store'), $this->payload($borrower, 'Existing primary key'))->assertSessionHasNoErrors();
        $old = $this->state();
        $oldId = BorrowingRequest::sole()->id;
        BorrowingRequest::creating(static function (BorrowingRequest $request) use ($oldId): void {
            $request->id = $oldId;
        });
        $service = \Mockery::mock(BorrowingRequestNumberService::class)->makePartial();
        $service->shouldReceive('newNumber')->once()->andReturn('BR-UNRELATED-PRIMARY-KEY');
        $this->app->instance(BorrowingRequestNumberService::class, $service);
        $this->withoutExceptionHandling();
        try {
            $this->post(route('requests.store'), $this->payload($borrower, 'Primary key write failure'));
            $this->fail('Unrelated unique failure must propagate.');
        } catch (UniqueConstraintViolationException $exception) {
            $this->assertTrue($exception->index === 'PRIMARY' || $exception->columns === ['id']);
            $this->assertSame($old, $this->state());
        }
    }

    public function test_largest_unsigned_borrower_reference_fits_existing_number_column(): void
    {
        $number = app(BorrowingRequestNumberService::class)->newNumber('18446744073709551615');
        $this->assertStringStartsWith('BR-'.now()->format('YmdHis').'-18446744073709551615-', $number);
        $this->assertLessThanOrEqual(50, strlen($number));
        $this->assertMatchesRegularExpression('/-[A-Z0-9]{10}$/D', $number);
    }

    #[DataProvider('failedWrites')]
    public function test_failed_second_create_preserves_old_rows_and_files_and_allows_same_token_retry(string $failure): void
    {
        $borrower = $this->borrower(true);
        $this->signature();
        $this->post(route('requests.store'), $this->payload($borrower, 'Existing signed record', true) + $this->letter('old'))->assertSessionHasNoErrors();
        $old = $this->state();
        $enabled = true;
        if ($failure === 'metadata') {
            StoredFile::creating(function () use (&$enabled): void {
                if ($enabled) {
                    throw new \RuntimeException('Synthetic late private metadata failure');
                }
            });
        } else {
            $real = app(AuditService::class);
            $this->mock(AuditService::class)->shouldReceive('record')->andReturnUsing(function (...$args) use (&$enabled, $real) {
                if ($enabled && $args[0] === 'REQUEST_SUBMITTED') {
                    throw new \RuntimeException('Synthetic late submission audit failure');
                }

                return $real->record(...$args);
            });
        }
        $payload = $this->payload($borrower, 'Recoverable second event', true) + ['_action_token' => 'req01a-failed-action'];
        $this->post(route('requests.store'), $payload + $this->letter('failed'))->assertStatus(500);
        $this->assertSame($old, $this->state());
        $enabled = false;
        $this->post(route('requests.store'), $payload + $this->letter('failed'))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('borrowing_requests', 2);
        $this->assertDatabaseCount('request_versions', 2);
        $this->assertDatabaseCount('signature_snapshots', 2);
        $this->assertOldStatePreserved($old);
        $this->assertNoReservation();
    }

    public static function failedWrites(): array
    {
        return [['metadata'], ['audit']];
    }

    private function borrower(bool $external): User
    {
        if ($external) {
            $user = User::factory()->create(['employee_no' => null, 'employment_type' => null, 'organizational_unit_id' => null, 'access_classification' => AccessClassification::BorrowerOnly]);
            BorrowerRegistration::create(['user_id' => $user->id, 'registration_type' => 'EXTERNAL', 'company_name' => 'Reviewed Request Number Agency', 'status' => 'APPROVED']);
            app(UserRoleAssignmentService::class)->synchronize($user, AccessClassification::BorrowerOnly, User::where('email', 'spmu@spmu.test')->sole()->id);
        } else {
            $user = User::where('email', 'borrower@spmu.test')->sole();
        }
        $this->actingAs($user)->withSession(['active_workspace' => 'BORROWER']);

        return $user;
    }

    private function signature(): void
    {
        $this->post(route('profile.signature'), ['signature' => UploadedFile::fake()->createWithContent('signature.png', EvidenceFixtures::signatureBytes('Request number synthetic ink'))])->assertSessionHasNoErrors();
        Cache::flush();
    }

    private function payload(User $borrower, string $purpose, bool $submit = false, bool $offCampus = false): array
    {
        $item = InventoryItem::where('unique_description', $offCampus ? 'Barricade' : 'Monoblock Chairs')->sole();
        $date = now()->addWeekdays(3);

        return ['purpose_event' => $purpose, 'location' => 'Synthetic request number venue',
            'schedule_date' => $date->toDateString(), 'return_date' => $date->copy()->addWeekdays(2)->toDateString(),
            'item_ids' => [$item->id], 'quantities' => [$item->id => 1], 'locations' => [$item->id => $offCampus ? 'OFF_CAMPUS' : 'ON_CAMPUS'],
            'intent' => $submit ? 'submit' : 'draft', 'borrower_acknowledgement' => 1, 'confirm_e_signature' => 1]
            + ($borrower->organizational_unit_id ? ['requesting_organizational_unit_id' => $borrower->organizational_unit_id] : []);
    }

    private function letter(string $tag): array
    {
        // A genuine UploadedFile avoids the fake File's public stream resource,
        // which is not JSON encodable in the middleware's payload fingerprint.
        $path = tempnam(sys_get_temp_dir(), 'req01a-test-');
        file_put_contents($path, EvidenceFixtures::pdfBytes('Synthetic '.$tag.' request letter'));
        $this->beforeApplicationDestroyed(static function () use ($path): void {
            if (is_file($path)) {
                unlink($path);
            }
        });

        return ['approved_request_letter' => new UploadedFile($path, $tag.'.pdf', 'application/pdf', null, true)];
    }

    private function state(): array
    {
        $rows = [];
        foreach (['borrowing_requests', 'request_versions', 'request_items', 'request_supporting_documents', 'signature_snapshots', 'stored_files', 'approval_steps', 'audit_events', 'notification_events', 'notification_deliveries', 'request_status_histories', 'allocations', 'custody_transactions', 'generated_documents'] as $table) {
            $rows[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }
        $files = [];
        foreach (Storage::disk('local')->allFiles() as $path) {
            $files[$path] = hash('sha256', Storage::disk('local')->get($path));
        }
        ksort($files);

        return ['rows' => $rows, 'files' => $files];
    }

    private function assertOldStatePreserved(array $old): void
    {
        $current = $this->state();
        foreach ($old['rows'] as $table => $rows) {
            foreach ($rows as $row) {
                $this->assertContains($row, $current['rows'][$table], $table.' original row changed');
            }
        }
        foreach ($old['files'] as $path => $hash) {
            $this->assertSame($hash, $current['files'][$path] ?? null, 'Original private bytes changed');
        }
    }

    private function assertNoReservation(): void
    {
        $this->assertDatabaseCount('allocations', 0);
        $this->assertDatabaseCount('custody_transactions', 0);
        $this->assertDatabaseCount('generated_documents', 0);
    }
}
