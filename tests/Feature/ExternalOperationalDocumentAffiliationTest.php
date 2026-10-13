<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Enums\EmploymentType;
use App\Enums\RegistrationType;
use App\Enums\RequestStatus;
use App\Models\Allocation;
use App\Models\ApprovalStep;
use App\Models\BorrowerRegistration;
use App\Models\BorrowingRequest;
use App\Models\CustodyLine;
use App\Models\CustodyTransaction;
use App\Models\GeneratedDocument;
use App\Models\InventoryItem;
use App\Models\RequestItem;
use App\Models\RequestVersion;
use App\Models\SignatureSnapshot;
use App\Models\StoredFile;
use App\Models\User;
use App\Models\UserSignature;
use App\Services\DocumentAffiliationService;
use App\Services\DocumentService;
use App\Services\ProtectedFileService;
use App\Services\SignatureService;
use App\Services\UserRoleAssignmentService;
use Database\Seeders\DatabaseSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\Support\EvidenceFixtures;
use Tests\TestCase;

class ExternalOperationalDocumentAffiliationTest extends TestCase
{
    public function test_new_borrower_slip_refers_to_the_current_admin_schedule_without_fixed_hours(): void
    {
        $custody = $this->approvedFixture();
        $html = $this->html($custody);
        $this->assertStringContainsString('Admin Transaction Schedule', $html);
        $this->assertStringContainsString('special-date changes', $html);
        $this->assertStringNotContainsString('1:00 to 4:00', $html);
        $this->assertStringNotContainsString('08:00', $html);
    }

    public static function descriptiveReports(): array
    {
        return [['borrowing'], ['approval'], ['custody'], ['returns'], ['gate-pass']];
    }

    #[DataProvider('descriptiveReports')]
    public function test_descriptive_reports_show_saved_external_company_without_fabricated_cspc_fields(string $key): void
    {
        $custody = $this->approvedFixture();
        $custody->request->currentVersion->update(['division_code' => 'ACADEMIC', 'office_unit' => 'College of Computer Studies']);
        $custody->borrower->borrowerRegistration()->update(['company_name' => 'Changed Profile Company']);
        $filters = \App\Reports\ReportFilters::fromRequest(\Illuminate\Http\Request::create('/reports'), $key, now()->startOfMonth(), now()->endOfMonth(), 'month');
        $dataset = app(\App\Services\ReportService::class)->generate($filters, $this->headUser());
        $this->assertSame($key, $dataset->reportKey);
        $this->assertSame(1, $dataset->count());
        $row = $dataset->rows->first();
        $this->assertSame('External Borrower', $row['division']);
        $this->assertSame('Reviewed Agency', $row['office_unit']);
        $this->assertSame('', $row['_division_code']);
        $this->assertSame('', $row['_office_unit']);
        $this->assertStringNotContainsString('Changed Profile Company', json_encode($row));
        $filtered = \App\Reports\ReportFilters::fromRequest(\Illuminate\Http\Request::create('/reports', 'GET', ['division' => 'ACADEMIC']), $key, now()->startOfMonth(), now()->endOfMonth(), 'month');
        $this->assertSame(0, app(\App\Services\ReportService::class)->generate($filtered)->count());
    }

    public static function accountabilityCaseTypes(): array
    {
        return [['PROPERTY'], ['LATE_RETURN']];
    }

    #[DataProvider('accountabilityCaseTypes')]
    public function test_accountability_report_preserves_saved_external_company_and_excludes_employee_filters(string $type): void
    {
        // Synthetic historical report rows; this does not certify a physical return.
        $custody = $this->approvedFixture();
        $custody->request->currentVersion->update(['division_code' => 'ACADEMIC', 'office_unit' => 'College of Computer Studies']);
        $custody->borrower->borrowerRegistration()->update(['company_name' => 'Changed Profile Company']);
        if ($type === 'PROPERTY') {
            \App\Models\Incident::create(['incident_no' => 'SYNTH-CASE-'.uniqid(),
                'custody_transaction_id' => $custody->id, 'borrower_user_id' => $custody->borrower_user_id,
                'incident_type' => 'DAMAGED', 'reported_by_user_id' => $this->headUser()->id,
                'reported_at' => now(), 'status' => 'OPEN']);
        } else {
            \App\Models\OverdueCase::create(['custody_transaction_id' => $custody->id,
                'borrower_user_id' => $custody->borrower_user_id, 'overdue_started_at' => now()->subDay(),
                'grace_expires_at' => now()->subDays(2), 'actual_return_date' => now()->toDateString(), 'status' => 'FOR_HEAD_APPROVAL']);
        }
        $filters = \App\Reports\ReportFilters::fromRequest(\Illuminate\Http\Request::create('/reports'),
            'accountability-cases', now()->startOfMonth(), now()->endOfMonth(), 'month');
        $dataset = app(\App\Services\ReportService::class)->generate($filters);
        $this->assertSame(1, $dataset->count());
        $row = $dataset->rows->first();
        $this->assertSame('External Borrower', $row['division']);
        $this->assertSame('Reviewed Agency', $row['office_unit']);
        $this->assertStringNotContainsString('Changed Profile Company', json_encode($row));
        foreach (['division' => 'ACADEMIC', 'unit' => 'College of Computer Studies'] as $name => $value) {
            $employeeFilter = \App\Reports\ReportFilters::fromRequest(\Illuminate\Http\Request::create('/reports', 'GET', [$name => $value]),
                'accountability-cases', now()->startOfMonth(), now()->endOfMonth(), 'month');
            $this->assertSame(0, app(\App\Services\ReportService::class)->generate($employeeFilter)->count());
        }
    }

    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.seed_demo_users' => true]);
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        Storage::fake('local');
    }

    public function test_resolver_uses_only_saved_attributes_without_queries_or_mutation(): void
    {
        $version = new RequestVersion(['affiliation_type' => RegistrationType::External,
            'external_company_name' => 'Reviewed Agency', 'division_code' => 'INCONSISTENT', 'office_unit' => 'Wrong CSPC Office']);
        $attributes = $version->getAttributes();
        DB::connection()->beforeExecuting(fn () => throw new \RuntimeException('Affiliation resolution must not query the database.'));
        $affiliation = app(DocumentAffiliationService::class)->resolve($version);
        $this->assertTrue($affiliation['is_external']);
        $this->assertFalse($affiliation['employee_checked']);
        $this->assertTrue($affiliation['others_checked']);
        $this->assertSame('Reviewed Agency', $affiliation['company_name']);
        $this->assertSame('External Borrower / Reviewed Agency', $affiliation['others_label']);
        $this->assertNull($affiliation['division_code']);
        $this->assertNull($affiliation['office_unit']);
        $this->assertSame($attributes, $version->getAttributes());
    }

    public static function employeeTypes(): array
    {
        return ['employee' => [EmploymentType::Employee, 'EMPLOYEE'], 'faculty' => [EmploymentType::Faculty, 'EMPLOYEE'],
            'staff' => [EmploymentType::Staff, 'EMPLOYEE'], 'legacy null affiliation' => [EmploymentType::Faculty, null]];
    }

    #[DataProvider('employeeTypes')]
    public function test_active_employee_classification_and_saved_unit_facts_remain_compatible(EmploymentType $type, ?string $affiliationType): void
    {
        $custody = $this->approvedFixture(false);
        $custody->request->borrower->update(['employment_type' => $type]);
        $custody->request->currentVersion->update(['affiliation_type' => $affiliationType,
            'external_company_name' => 'Inapplicable agency', 'office_unit' => 'Saved Employee Office', 'division_code' => 'ADMINISTRATIVE']);
        $version = $custody->request->currentVersion;
        $resolved = app(DocumentAffiliationService::class)->resolve($version);
        $this->assertFalse($resolved['is_external']);
        $this->assertSame('Saved Employee Office', $resolved['office_unit']);
        $this->assertSame('ADMINISTRATIVE', $resolved['division_code']);
        $this->assertNull($resolved['company_name']);
        $html = $this->html($custody);
        $this->classification($html, true, false, '');
        $this->assertStringNotContainsString('Inapplicable agency', $html);
        $this->assertStringNotContainsString('external-affiliation', $html);
    }

    public function test_external_active_slip_checks_others_and_prints_frozen_company_after_profile_edits(): void
    {
        $custody = $this->approvedFixture();
        $version = $custody->request->currentVersion;
        $versionAttributes = $version->getAttributes();
        $borrower = $custody->request->borrower;
        $borrower->borrowerRegistration->update(['company_name' => 'Later unreviewed agency']);
        $borrower->update(['organizational_unit_id' => $this->employee()->organizational_unit_id]);
        $html = $this->html($custody->fresh());
        $this->classification($html, false, true, 'External Borrower / Reviewed Agency');
        $this->assertStringNotContainsString('Later unreviewed agency', $html);
        $this->assertSame($versionAttributes, $version->fresh()->getAttributes());
        $this->assertNull($custody->request->fresh()->accountable_unit_id);
        $this->assertNull($version->fresh()->office_unit);
        $this->assertNull($version->fresh()->division_code);
    }

    public static function companyBoundaries(): array
    {
        return ['HTML sensitive' => ['Agency <script>alert("x")</script> & "Partners"'],
            '255 accented characters' => [mb_substr(str_repeat('Peña & Équipe Agency ', 20), 0, 255)],
            '255 unbroken characters' => [str_repeat('A', 255)]];
    }

    public function test_explicit_external_affiliation_wins_over_inconsistent_employee_fields(): void
    {
        $custody = $this->approvedFixture();
        $custody->request->currentVersion->update(['office_unit' => 'Inconsistent CSPC Office', 'division_code' => 'WRONG_DIVISION']);
        $custody->request->borrower->update(['employment_type' => EmploymentType::Faculty,
            'organizational_unit_id' => $this->employee()->organizational_unit_id]);
        $versionAttributes = $custody->request->currentVersion->fresh()->getAttributes();
        $html = $this->html($custody->fresh());
        $this->classification($html, false, true, 'External Borrower / Reviewed Agency');
        $this->assertStringNotContainsString('Inconsistent CSPC Office', $html);
        $this->assertStringNotContainsString('WRONG_DIVISION', $html);
        $this->assertSame($versionAttributes, $custody->request->currentVersion->fresh()->getAttributes());
    }

    #[DataProvider('companyBoundaries')]
    public function test_full_company_is_escaped_and_not_truncated(string $company): void
    {
        $custody = $this->approvedFixture(company: $company);
        $html = $this->html($custody);
        $this->classification($html, false, true, 'External Borrower / '.$company);
        $this->assertStringContainsString(e($company), $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public static function missingCompanies(): array
    {
        return ['null' => [null], 'empty' => [''], 'whitespace' => ['   ']];
    }

    #[DataProvider('missingCompanies')]
    public function test_known_external_missing_company_is_truthful_without_live_profile_fallback(?string $company): void
    {
        $custody = $this->approvedFixture(company: $company);
        $html = $this->html($custody);
        $this->classification($html, false, true, 'External Borrower / Affiliation not recorded');
        $this->assertStringNotContainsString('Current Profile Agency', $html);
    }

    public function test_unknown_explicit_affiliation_cannot_be_classified_as_employee(): void
    {
        $version = (new RequestVersion)->setRawAttributes(['affiliation_type' => 'UNKNOWN']);
        $this->expectException(ValidationException::class);
        app(DocumentAffiliationService::class)->resolve($version);
    }

    public function test_real_approved_generation_preserves_version_signatures_private_metadata_and_inventory(): void
    {
        // A synthetic already-approved fixture tests rendering, not the later actual approval increment.
        $custody = $this->approvedFixture();
        $snapshots = SignatureSnapshot::all()->mapWithKeys(fn ($s) => [$s->id => $s->getAttributes()]);
        $version = $custody->request->currentVersion;
        $versionAttributes = $version->getAttributes();
        $allocations = Allocation::all()->map->getAttributes()->all();
        $auditCount = DB::table('audit_events')->count();
        $noticeCount = DB::table('notification_events')->count();
        $html = $this->html($custody);
        $this->assertSame(2, SignatureSnapshot::count());
        foreach ([$version->borrowerSignature, $version->approvalSteps->sole()->signatureSnapshot] as $signature) {
            $this->assertStringContainsString(base64_encode(app(ProtectedFileService::class)->bytes($signature->file)), $html);
        }
        $document = app(DocumentService::class)->borrowerSlip($custody);
        $bytes = app(ProtectedFileService::class)->bytes($document->file);
        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertStringEndsWith('%%EOF', trim($bytes));
        $this->assertSame(hash('sha256', $bytes), $document->sha256);
        $this->assertSame($document->sha256, $document->file->sha256);
        $this->assertSame('local', $document->file->disk);
        $this->assertSame('CONTROLLED_DOCUMENT', $document->file->classification);
        $this->assertSame($version->id, $document->request_version_id);
        $this->assertSame($custody::class, $document->subject_type);
        $this->assertSame($custody->id, $document->subject_id);
        $this->assertSame('BORROWER_SLIP', $document->document_type);
        $this->assertSame('FINAL', $document->status);
        $this->assertSame(1, GeneratedDocument::count());
        $this->assertSame($versionAttributes, $version->fresh()->getAttributes());
        $this->assertSame($allocations, Allocation::all()->map->getAttributes()->all());
        $this->assertSame(1, CustodyTransaction::count());
        $this->assertSame(0, DB::table('gate_passes')->count());
        $this->assertSame(0, DB::table('laundry_jobs')->count());
        $this->assertSame($auditCount, DB::table('audit_events')->count());
        $this->assertSame($noticeCount, DB::table('notification_events')->count());
        foreach ($snapshots as $id => $attributes) {
            $this->assertSame($attributes, SignatureSnapshot::findOrFail($id)->getAttributes());
        }
    }

    public function test_profile_changes_do_not_rewrite_existing_pdf_and_access_stays_scoped(): void
    {
        $custody = $this->approvedFixture();
        $document = app(DocumentService::class)->borrowerSlip($custody)->fresh('file');
        $attributes = $document->getAttributes();
        $fileAttributes = $document->file->getAttributes();
        $bytes = app(ProtectedFileService::class)->bytes($document->file);
        $custody->request->borrower->borrowerRegistration->update(['company_name' => 'Later Profile Agency']);
        $owner = $custody->request->borrower;
        $this->actingAs($owner)->withSession(['active_workspace' => 'BORROWER'])
            ->get(route('documents.download', $document))->assertOk()->assertStreamedContent($bytes);
        $this->actingAs($this->employee())->withSession(['active_workspace' => 'BORROWER'])
            ->get(route('documents.view', $document))->assertForbidden();
        $this->actingAs($this->headUser())->withSession(['active_workspace' => 'SPMU'])
            ->get(route('documents.view', $document))->assertOk()->assertStreamedContent($bytes);
        $this->assertSame($attributes, $document->fresh()->getAttributes());
        $this->assertSame($fileAttributes, $document->file->fresh()->getAttributes());
        $this->assertSame($bytes, app(ProtectedFileService::class)->bytes($document->file));
        $this->assertSame(1, GeneratedDocument::count());
    }

    public function test_preapproval_generation_remains_blocked_without_any_new_files(): void
    {
        $custody = $this->approvedFixture();
        $custody->request->update(['status' => RequestStatus::UnderSpmu, 'final_approved_at' => null]);
        $fileCount = StoredFile::count();
        $paths = Storage::disk('local')->allFiles();
        try {
            app(DocumentService::class)->borrowerSlip($custody->fresh());
            $this->fail('Operational generation must require final approval.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('document', $exception->errors());
        }
        $this->assertSame(0, GeneratedDocument::count());
        $this->assertSame($fileCount, StoredFile::count());
        $this->assertSame($paths, Storage::disk('local')->allFiles());
    }

    public function test_unknown_loaded_version_affiliation_does_not_supersede_an_existing_controlled_copy(): void
    {
        $custody = $this->approvedFixture();
        $document = app(DocumentService::class)->borrowerSlip($custody)->fresh('file');
        $attributes = $document->getAttributes();
        $bytes = app(ProtectedFileService::class)->bytes($document->file);
        $fileCount = StoredFile::count();
        // Simulate an unsupported loaded value without bypassing the database enum constraint.
        $version = $custody->request->currentVersion;
        $version->setRawAttributes(array_replace($version->getAttributes(), ['affiliation_type' => 'UNKNOWN']));
        try {
            app(DocumentService::class)->borrowerSlip($custody);
            $this->fail('Unknown saved affiliation must require review.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('affiliation', $exception->errors());
        }
        $this->assertSame($attributes, $document->fresh()->getAttributes());
        $this->assertSame($bytes, app(ProtectedFileService::class)->bytes($document->file));
        $this->assertSame($fileCount, StoredFile::count());
        $this->assertSame(1, GeneratedDocument::count());
    }

    private function html(CustodyTransaction $custody): string
    {
        $custody->loadMissing('request.borrower', 'request.currentVersion.borrowerSignature.file',
            'request.currentVersion.approvalSteps.approver', 'request.currentVersion.approvalSteps.signatureSnapshot.file', 'lines.requestItem.inventoryItem');

        return (new ReflectionMethod(DocumentService::class, 'borrowerSlipHtml'))->invoke(app(DocumentService::class), $custody);
    }

    private function classification(string $html, bool $employee, bool $others, string $label): void
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new DOMXPath($dom);
        $root = '//div[@class="borrower-classification"]';
        $this->assertSame(1, $xpath->query($root)->length);
        $boxes = $xpath->query($root.'//span[contains(concat(" ", @class, " "), " borrower-box ")]');
        $this->assertSame(2, $boxes->length);
        $this->assertSame($employee, str_contains($boxes->item(0)->getAttribute('class'), 'checked'));
        $this->assertSame($others, str_contains($boxes->item(1)->getAttribute('class'), 'checked'));
        $this->assertSame($label, trim($xpath->query($root.'//span[contains(@class,"borrower-others-line")]')->item(0)->textContent));
    }

    private function headUser(): User
    {
        return User::where('access_classification', AccessClassification::SpmuHead)->firstOrFail();
    }

    private function employee(): User
    {
        return User::where('access_classification', AccessClassification::BorrowerOnly)->whereNotNull('organizational_unit_id')->firstOrFail();
    }

    private function snapshot(User $user, string $purpose, string $role): SignatureSnapshot
    {
        $file = app(ProtectedFileService::class)->storeBytes(EvidenceFixtures::signatureBytes($role),
            'synthetic-signatures', 'signature.png', 'image/png', 'png', 'SIGNATURE', $user->id);
        UserSignature::create(['user_id' => $user->id, 'stored_file_id' => $file->id, 'effective_from' => now()->subMinute(), 'status' => 'ACTIVE']);
        $this->actingAs($user);

        return app(SignatureService::class)->snapshot($user, $purpose, $role);
    }

    private function approvedFixture(bool $external = true, ?string $company = 'Reviewed Agency'): CustodyTransaction
    {
        $borrower = $external ? User::factory()->create(['employee_no' => null, 'organizational_unit_id' => null,
            'employment_type' => null, 'employment_status' => null, 'designation' => null]) : $this->employee();
        if ($external) {
            app(UserRoleAssignmentService::class)->synchronize($borrower, AccessClassification::BorrowerOnly);
            BorrowerRegistration::create(['user_id' => $borrower->id, 'registration_type' => 'EXTERNAL', 'company_name' => 'Current Profile Agency', 'status' => 'APPROVED']);
        }
        $request = BorrowingRequest::create(['request_no' => 'SYNTH-AFF-'.uniqid(), 'borrower_user_id' => $borrower->id,
            'accountable_unit_id' => $external ? null : $borrower->organizational_unit_id, 'current_version_no' => 1,
            'status' => RequestStatus::ApprovedReadyForRelease, 'final_approved_at' => now()]);
        $version = $request->versions()->create(['version_no' => 1, 'purpose_event' => 'Synthetic approved rendering fixture',
            'location' => 'Synthetic venue', 'schedule_date' => now()->addDay()->toDateString(), 'return_date' => now()->addDays(2)->toDateString(),
            'needed_from' => now()->addDay()->startOfDay(), 'return_due_at' => now()->addDays(2)->endOfDay(),
            'created_by_user_id' => $borrower->id, 'affiliation_type' => $external ? 'EXTERNAL' : 'EMPLOYEE',
            'external_company_name' => $external ? $company : null]);
        $borrowerSignature = $this->snapshot($borrower, 'BORROWING_REQUEST', 'BORROWER');
        $version->update(['borrower_signature_snapshot_id' => $borrowerSignature->id, 'signed_at' => now(), 'submitted_at' => now()]);
        $headSignature = $this->snapshot($this->headUser(), 'SPMU_HEAD_APPROVAL', 'SPMU_HEAD');
        ApprovalStep::create(['request_version_id' => $version->id, 'stage_code' => 'SPMU', 'sequence_no' => 2, 'decision' => 'APPROVED',
            'approver_user_id' => $this->headUser()->id, 'signature_snapshot_id' => $headSignature->id, 'decided_at' => now()]);
        $item = InventoryItem::with('unit')->where('active', true)->where('borrowable', true)->where('laundry_required', false)->firstOrFail();
        $requestItem = RequestItem::create(['request_version_id' => $version->id, 'inventory_item_id' => $item->id,
            'description_snapshot' => $item->unique_description, 'unit_snapshot' => $item->unit->unit_name,
            'requested_quantity' => 1, 'approved_quantity' => 1, 'use_location' => 'ON_CAMPUS']);
        $allocation = Allocation::create(['request_item_id' => $requestItem->id, 'period_start' => $version->needed_from,
            'period_end' => $version->return_due_at, 'allocated_quantity' => 1, 'released_quantity' => 0, 'restored_quantity' => 0,
            'status' => 'ACTIVE', 'allocated_at' => now()]);
        $custody = CustodyTransaction::create(['custody_no' => 'SYNTH-CUS-'.uniqid(), 'request_id' => $request->id,
            'request_version_id' => $version->id, 'borrower_user_id' => $borrower->id, 'status' => 'PREPARING_RELEASE', 'due_at' => $version->return_due_at]);
        CustodyLine::create(['custody_transaction_id' => $custody->id, 'request_item_id' => $requestItem->id, 'allocation_id' => $allocation->id,
            'approved_quantity' => 1, 'quantity_to_receive' => 1, 'actual_released_quantity' => 0, 'returned_quantity' => 0, 'item_status' => 'PENDING']);

        return $custody->fresh();
    }
}
