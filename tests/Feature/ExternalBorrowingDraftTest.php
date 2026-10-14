<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Enums\RegistrationType;
use App\Enums\RequestStatus;
use App\Models\BorrowerRegistration;
use App\Models\BorrowingRequest;
use App\Models\InventoryItem;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\AuditService;
use App\Services\ProtectedFileService;
use App\Services\UserRoleAssignmentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExternalBorrowingDraftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.seed_demo_users' => true]);
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        Storage::fake('local');
    }

    public function test_external_form_shows_approved_company_without_cspc_assignment_fields(): void
    {
        $borrower = $this->externalBorrower();
        // A stray internal grant must not turn an external account into an employee.
        $unit = OrganizationalUnit::query()->where('unit_code', 'CCS')->firstOrFail();
        $borrower->update(['organizational_unit_id' => $unit->id]);
        $borrower->authorizedOrganizationalUnits()->attach($unit->id, ['assignment_type' => 'ADDITIONAL', 'assigned_at' => now()]);
        $this->actingAs($borrower->fresh())->get(route('requests.create'))->assertOk()
            ->assertSee('Example External Agency')->assertSee('id="external-company-name"', false)
            ->assertDontSee('name="requesting_organizational_unit_id"', false)
            ->assertDontSee('name="division_code"', false)->assertDontSee('name="office_unit"', false);
    }

    public function test_external_can_save_a_draft_with_company_snapshot_and_no_internal_assignment(): void
    {
        $borrower = $this->externalBorrower();
        $this->saveDraft($borrower);
        $draft = BorrowingRequest::query()->firstOrFail();
        $version = $draft->currentVersion;
        $this->assertSame(RequestStatus::Draft, $draft->status);
        $this->assertNull($draft->accountable_unit_id);
        $this->assertNull($version->division_code);
        $this->assertNull($version->office_unit);
        $this->assertSame(RegistrationType::External, $version->affiliation_type);
        $this->assertSame('Example External Agency', $version->external_company_name);
        $this->assertDatabaseMissing('user_organizational_units', ['user_id' => $borrower->id]);
        $this->assertSame(1, $borrower->roles()->wherePivotNull('revoked_at')->count());
        $this->assertDatabaseCount('allocations', 0);
        $this->assertDatabaseHas('audit_events', ['action_code' => 'REQUEST_DRAFT_CREATED', 'actor_user_id' => $borrower->id, 'record_id' => $draft->id]);
    }

    public function test_saved_external_draft_reopens_and_edits_without_rewriting_affiliation(): void
    {
        $borrower = $this->externalBorrower();
        $this->saveDraft($borrower);
        $draft = BorrowingRequest::query()->firstOrFail();
        $borrower->borrowerRegistration()->update(['company_name' => 'Later Profile Agency']);
        $this->get(route('requests.edit', $draft))->assertOk()->assertSee('Example External Agency')
            ->assertDontSee('Later Profile Agency')->assertSee('const resumeStage = 3;', false);
        $this->put(route('requests.update', $draft), [...$this->payload(), 'purpose_event' => 'Updated external event'])
            ->assertSessionHasNoErrors()->assertRedirect(route('requests.index'));
        $this->assertSame('Example External Agency', $draft->fresh()->currentVersion->external_company_name);
        $this->assertNull($draft->fresh()->accountable_unit_id);
        $this->assertDatabaseHas('audit_events', ['action_code' => 'REQUEST_DRAFT_UPDATED', 'actor_user_id' => $borrower->id]);
        $this->get(route('requests.show', $draft))->assertRedirect(route('requests.edit', $draft));
        $this->get(route('requests.edit', $draft))->assertOk()->assertSee('Company / Agency / Institution')
            ->assertSee('Example External Agency')->assertDontSee('Later Profile Agency');
        $this->get(route('requests.index'))->assertOk()->assertSee('Example External Agency');
    }

    public function test_returned_revision_retains_external_snapshot_and_prior_version(): void
    {
        $borrower = $this->externalBorrower();
        $this->saveDraft($borrower);
        $draft = BorrowingRequest::query()->firstOrFail();
        $old = $draft->currentVersion;
        $old->update(['signed_at' => now()]);
        $draft->update(['status' => RequestStatus::ReturnedForRevision]);
        $borrower->borrowerRegistration()->update(['company_name' => 'Later Profile Agency']);
        $this->put(route('requests.update', $draft), [...$this->payload(), 'purpose_event' => 'Returned correction'])->assertSessionHasNoErrors();
        $this->assertSame(2, $draft->fresh()->current_version_no);
        $this->assertSame(2, $draft->versions()->count());
        $this->assertSame('Example External Agency', $old->fresh()->external_company_name);
        $this->assertNotNull($old->fresh()->signed_at);
        $this->assertSame('Example External Agency', $draft->fresh()->currentVersion->external_company_name);
        $this->assertNull($draft->fresh()->currentVersion->signed_at);
    }

    public function test_returned_request_detail_uses_saved_external_company(): void
    {
        $borrower = $this->externalBorrower();
        $this->saveDraft($borrower);
        $draft = BorrowingRequest::query()->firstOrFail();
        $draft->update(['status' => RequestStatus::ReturnedForRevision]);
        $borrower->borrowerRegistration()->update(['company_name' => 'Later Profile Agency']);
        $this->get(route('requests.show', $draft))->assertOk()->assertSee('Company / Agency / Institution')
            ->assertSee('Example External Agency')->assertDontSee('Later Profile Agency');
    }

    public static function forgedAffiliationFields(): array
    {
        return [['requesting_organizational_unit_id', 1], ['accountable_unit_id', 1], ['organizational_unit_id', 1],
            ['division_code', 'ACADEMIC'], ['office_unit', 'College of Computer Studies (CCS)'],
            ['affiliation_type', 'EMPLOYEE'], ['external_company_name', 'Forged Company'], ['company_name', 'Forged Company']];
    }

    #[DataProvider('forgedAffiliationFields')]
    public function test_external_affiliation_overrides_are_rejected_without_writes(string $field, mixed $value): void
    {
        $this->actingAs($this->externalBorrower())->post(route('requests.store'), [...$this->payload(), $field => $value])
            ->assertSessionHasErrors($field);
        $this->assertDatabaseCount('borrowing_requests', 0);
        $this->assertDatabaseCount('request_versions', 0);
    }

    public function test_external_draft_update_cannot_inject_a_cspc_assignment(): void
    {
        $this->saveDraft($this->externalBorrower());
        $draft = BorrowingRequest::query()->firstOrFail();
        $this->put(route('requests.update', $draft), [...$this->payload(), 'requesting_organizational_unit_id' => 1])
            ->assertSessionHasErrors('requesting_organizational_unit_id');
        $this->assertNull($draft->fresh()->accountable_unit_id);
        $this->assertSame('Example External Agency', $draft->fresh()->currentVersion->external_company_name);
    }

    public function test_unitless_legacy_account_does_not_become_external(): void
    {
        $user = User::factory()->create(['organizational_unit_id' => null]);
        $this->actingAs($user)->post(route('requests.store'), $this->payload())->assertSessionHasErrors('requesting_organizational_unit_id');
        $this->assertDatabaseCount('borrowing_requests', 0);
    }

    public static function invalidEmployeeAssignments(): array
    {
        return [['inactive_primary'], ['revoked_additional']];
    }

    #[DataProvider('invalidEmployeeAssignments')]
    public function test_employee_inactive_or_revoked_assignments_remain_forbidden(string $case): void
    {
        $borrower = User::query()->where('email', 'borrower@spmu.test')->firstOrFail();
        $unit = OrganizationalUnit::query()->where('unit_code', $case === 'inactive_primary' ? 'CCS' : 'CHS')->firstOrFail();
        if ($case === 'inactive_primary') {
            $unit->update(['active' => false]);
        } else {
            $borrower->authorizedOrganizationalUnits()->attach($unit->id, [
                'assignment_type' => 'ADDITIONAL', 'assigned_at' => now()->subDay(), 'revoked_at' => now()->subMinute(),
            ]);
        }
        $this->actingAs($borrower)->post(route('requests.store'), [...$this->payload(), 'requesting_organizational_unit_id' => $unit->id])
            ->assertSessionHasErrors('requesting_organizational_unit_id');
        $this->assertDatabaseCount('borrowing_requests', 0);
    }

    public function test_employee_cannot_choose_external_affiliation_through_post_fields(): void
    {
        $borrower = User::query()->where('email', 'borrower@spmu.test')->firstOrFail();
        $this->actingAs($borrower)->post(route('requests.store'), [...$this->payload(),
            'requesting_organizational_unit_id' => $borrower->organizational_unit_id,
            'affiliation_type' => 'EXTERNAL', 'external_company_name' => 'Unapproved Agency',
        ])->assertSessionHasErrors(['affiliation_type', 'external_company_name']);
        $this->assertDatabaseCount('borrowing_requests', 0);
    }

    public function test_account_type_drift_cannot_reclassify_saved_external_affiliation(): void
    {
        $borrower = $this->externalBorrower();
        $this->saveDraft($borrower);
        $draft = BorrowingRequest::query()->firstOrFail();
        $borrower->borrowerRegistration()->update(['registration_type' => 'EMPLOYEE']);
        $unit = OrganizationalUnit::query()->where('unit_code', 'CCS')->firstOrFail();
        $borrower->update(['organizational_unit_id' => $unit->id]);
        $this->put(route('requests.update', $draft), [...$this->payload(), 'requesting_organizational_unit_id' => $unit->id])
            ->assertSessionHasErrors('affiliation');
        $this->assertNull($draft->fresh()->accountable_unit_id);
        $this->assertSame(RegistrationType::External, $draft->fresh()->currentVersion->affiliation_type);
    }

    public function test_guest_cannot_create_an_external_draft(): void
    {
        $this->post(route('requests.store'), $this->payload())->assertRedirect(route('login'));
        $this->assertDatabaseCount('borrowing_requests', 0);
    }

    public function test_missing_approved_company_blocks_new_draft(): void
    {
        $borrower = $this->externalBorrower();
        $borrower->borrowerRegistration()->update(['company_name' => null]);
        $this->actingAs($borrower)->post(route('requests.store'), $this->payload())->assertSessionHasErrors('affiliation');
        $this->assertDatabaseCount('borrowing_requests', 0);
    }

    public function test_pending_external_account_cannot_create_drafts(): void
    {
        $borrower = $this->externalBorrower();
        $borrower->borrowerRegistration()->update(['status' => 'PENDING']);
        $this->actingAs($borrower)->post(route('requests.store'), $this->payload())->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseCount('borrowing_requests', 0);
    }

    public function test_another_borrower_cannot_edit_the_external_draft(): void
    {
        $this->saveDraft($this->externalBorrower());
        $draft = BorrowingRequest::query()->firstOrFail();
        $other = User::query()->where('email', 'borrower@spmu.test')->firstOrFail();
        $this->actingAs($other)->get(route('requests.edit', $draft))->assertForbidden();
        $this->put(route('requests.update', $draft), $this->payload())->assertForbidden();
        $this->assertSame('External draft event', $draft->fresh()->currentVersion->purpose_event);
    }

    public function test_invalid_dates_and_items_leave_no_external_request(): void
    {
        $borrower = $this->externalBorrower();
        $data = $this->payload();
        $this->actingAs($borrower)->post(route('requests.store'), [...$data, 'return_date' => $data['schedule_date']])->assertSessionHasErrors('return_date');
        $itemId = $data['item_ids'][0];
        $this->post(route('requests.store'), [...$data, 'quantities' => [$itemId => 0]])->assertSessionHasErrors();
        $this->assertDatabaseCount('borrowing_requests', 0);
        $this->assertDatabaseCount('request_versions', 0);
    }

    public function test_draft_audit_failure_rolls_back_header_version_and_items(): void
    {
        $borrower = $this->externalBorrower();
        $this->mock(AuditService::class, fn ($mock) => $mock->shouldReceive('record')->andThrow(new \RuntimeException('Synthetic draft audit failure')));
        $this->actingAs($borrower)->post(route('requests.store'), $this->payload())->assertStatus(500);
        $this->assertDatabaseCount('borrowing_requests', 0);
        $this->assertDatabaseCount('request_versions', 0);
        $this->assertDatabaseCount('request_items', 0);
    }

    public function test_update_audit_failure_preserves_existing_draft(): void
    {
        $this->saveDraft($this->externalBorrower());
        $draft = BorrowingRequest::query()->firstOrFail();
        $this->mock(AuditService::class, fn ($mock) => $mock->shouldReceive('record')->andThrow(new \RuntimeException('Synthetic draft update audit failure')));
        $this->put(route('requests.update', $draft), [...$this->payload(), 'purpose_event' => 'Failed update'])->assertStatus(500);
        $this->assertSame('External draft event', $draft->fresh()->currentVersion->purpose_event);
        $this->assertSame(1, $draft->currentVersion->items()->count());
        $this->assertNull($draft->fresh()->accountable_unit_id);
    }

    public function test_external_company_cannot_be_counted_as_a_cspc_unit(): void
    {
        $borrower = $this->externalBorrower();
        $unit = OrganizationalUnit::query()->where('unit_code', 'CCS')->firstOrFail();
        $borrower->borrowerRegistration()->update(['company_name' => $unit->unit_name]);
        $this->saveDraft($borrower);
        $draft = BorrowingRequest::query()->firstOrFail();
        // A reporting fixture; this does not simulate or authorize real submission.
        $draft->update(['status' => RequestStatus::UnderSpmu]);
        $draft->currentVersion->update(['submitted_at' => now()]);
        $ranks = app(AnalyticsService::class)->unitRankings(now()->startOfDay(), now()->endOfDay(), null, null);
        $this->assertSame([], $ranks['columns']->all());
        $filtered = app(AnalyticsService::class)->requestScope(now()->startOfDay(), now()->endOfDay(), 'ACADEMIC', $unit->unit_name);
        $this->assertSame(0, $filtered->count());
    }

    public function test_rollback_refuses_to_remove_saved_affiliation(): void
    {
        $this->saveDraft($this->externalBorrower());
        $migration = require database_path('migrations/2026_10_12_000002_add_external_request_affiliation.php');
        try {
            $migration->down();
            $this->fail('Rollback must preserve saved affiliation.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('preserved', $exception->getMessage());
        }
        $this->assertDatabaseCount('borrowing_requests', 1);
        $this->assertDatabaseHas('request_versions', ['external_company_name' => 'Example External Agency']);
    }

    private function externalBorrower(): User
    {
        $user = User::factory()->create(['employee_no' => null, 'employment_type' => null,
            'organizational_unit_id' => null, 'access_classification' => AccessClassification::BorrowerOnly]);
        $officer = User::query()->where('email', 'spmu@spmu.test')->firstOrFail();
        $file = app(ProtectedFileService::class)->storeUpload(UploadedFile::fake()->image('synthetic-id.png'), 'external-fixtures', 'BORROWER_REGISTRATION_ID');
        $file->update(['uploaded_by_user_id' => $user->id]);
        BorrowerRegistration::query()->create(['user_id' => $user->id, 'registration_type' => 'EXTERNAL',
            'company_name' => 'Example External Agency', 'valid_id_file_id' => $file->id, 'status' => 'APPROVED']);
        app(UserRoleAssignmentService::class)->synchronize($user, AccessClassification::BorrowerOnly, $officer->id);

        return $user;
    }

    private function payload(): array
    {
        $item = InventoryItem::query()->where('active', true)->where('borrowable', true)->where('condition_code', 'SERVICEABLE')->firstOrFail();
        $from = now()->addWeekday();

        return ['purpose_event' => 'External draft event', 'location' => 'Local fixture venue',
            'schedule_date' => $from->toDateString(), 'return_date' => $from->copy()->addWeekday()->toDateString(),
            'intent' => 'draft', 'item_ids' => [$item->id], 'quantities' => [$item->id => 1], 'locations' => [$item->id => 'ON_CAMPUS']];
    }

    private function saveDraft(User $borrower): void
    {
        $this->actingAs($borrower)->withSession(['active_workspace' => 'BORROWER'])
            ->post(route('requests.store'), $this->payload())->assertSessionHasNoErrors()->assertRedirect(route('requests.index'));
    }
}
