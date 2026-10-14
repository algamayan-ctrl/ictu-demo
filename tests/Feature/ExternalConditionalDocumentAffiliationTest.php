<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Enums\RequestStatus;
use App\Models\CustodyTransaction;
use App\Models\GeneratedDocument;
use App\Models\LaundryJob;
use App\Models\LaundryJobLine;
use App\Models\StoredFile;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\ProtectedFileService;
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
use Tests\Support\ApprovedConditionalDocumentFixture;
use Tests\TestCase;

class ExternalConditionalDocumentAffiliationTest extends TestCase
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

    public static function forms(): array
    {
        return ['laundry' => ['LAUNDRY_FORM'], 'gate pass' => ['GATE_PASS']];
    }

    #[DataProvider('forms')]
    public function test_active_form_uses_reviewed_company_after_later_profile_and_inconsistent_unit_changes(string $form): void
    {
        $custody = $this->fixture($form);
        $version = $custody->request->currentVersion;
        $version->update(['office_unit' => 'Incorrect Version CSPC Office', 'represented_program_department' => 'Incorrect Program']);
        $borrower = $custody->request->borrower;
        $borrower->borrowerRegistration->update(['company_name' => 'Later Profile Agency']);
        $borrower->update(['organizational_unit_id' => $this->employee()->organizational_unit_id]);
        $versionAttributes = $version->fresh()->getAttributes();
        $xpath = $this->html($custody->fresh(), $form);
        $this->assertSame('Reviewed Agency', $this->affiliation($xpath, $form));
        $this->assertSame($versionAttributes, $version->fresh()->getAttributes());
        $this->assertNull($custody->request->fresh()->accountable_unit_id);
        $this->assertStringNotContainsString('Later Profile Agency', $xpath->document->textContent);
        $this->assertStringNotContainsString('Incorrect Version CSPC Office', $xpath->document->textContent);
        $this->assertStringNotContainsString('Incorrect Program', $xpath->document->textContent);
        if ($form === 'GATE_PASS') {
            $this->assertSame($borrower->full_name, trim($xpath->query('//table[@class="gp-bearer-block"]//div[@class="gp-signature-name"]')->item(0)->textContent));
        }
    }

    public static function companies(): array
    {
        $cases = [];
        foreach (['LAUNDRY_FORM', 'GATE_PASS'] as $form) {
            foreach (['sensitive' => 'Agency <script>alert("x")</script> & "Partners"',
                '255 accented' => mb_substr(str_repeat('Peña & Équipe Agency ', 20), 0, 255),
                '255 unbroken' => str_repeat('A', 255), 'null' => null, 'empty' => '', 'spaces' => '   '] as $name => $company) {
                $cases[$form.' '.$name] = [$form, $company];
            }
        }

        return $cases;
    }

    #[DataProvider('companies')]
    public function test_full_company_or_truthful_missing_marker_is_rendered_without_html_injection(string $form, ?string $company): void
    {
        $custody = $this->fixture($form, company: $company);
        $xpath = $this->html($custody, $form);
        $this->assertSame(trim((string) $company) ?: 'External Borrower / Affiliation not recorded', $this->affiliation($xpath, $form));
        $this->assertSame(0, $xpath->query('//script')->length);
        $this->assertStringNotContainsString('Current Profile Agency', $xpath->document->textContent);
    }

    public static function employees(): array
    {
        return ['saved office employee' => ['EMPLOYEE', 'EMPLOYEE', 'Saved Office', 'Saved Program', true, 'Saved Office'],
            'represented program faculty' => ['EMPLOYEE', 'FACULTY', null, 'Saved Program', true, 'Saved Program'],
            'profile office staff' => ['EMPLOYEE', 'STAFF', null, null, true, null],
            'blank office employee' => ['EMPLOYEE', 'EMPLOYEE', null, null, false, ''],
            'legacy null faculty' => [null, 'FACULTY', 'Legacy Saved Office', null, true, 'Legacy Saved Office']];
    }

    #[DataProvider('employees')]
    public function test_employee_laundry_fallback_and_gate_personal_name_behavior_are_preserved(?string $type, string $employment, ?string $office, ?string $program, bool $hasProfileUnit, ?string $expected): void
    {
        $custody = $this->fixture('LAUNDRY_FORM', external: false);
        $borrower = $custody->request->borrower;
        $expected ??= $borrower->organizationalUnit->unit_name;
        $borrower->update(['employment_type' => $employment, 'organizational_unit_id' => $hasProfileUnit ? $borrower->organizational_unit_id : null]);
        $custody->request->currentVersion->update(['affiliation_type' => $type, 'office_unit' => $office,
            'represented_program_department' => $program, 'external_company_name' => 'Inapplicable Agency']);
        $xpath = $this->html($custody->fresh(), 'LAUNDRY_FORM');
        $this->assertSame($expected, $this->affiliation($xpath, 'LAUNDRY_FORM'));
        $this->assertStringNotContainsString('Inapplicable Agency', $xpath->document->textContent);
        $gate = $this->html($custody, 'GATE_PASS');
        $this->assertSame(0, $gate->query('//div[@class="gp-signature-affiliation"]')->length);
        $this->assertSame($borrower->full_name, trim($gate->query('//table[@class="gp-bearer-block"]//div[@class="gp-signature-name"]')->item(0)->textContent));
    }

    #[DataProvider('forms')]
    public function test_real_private_generation_preserves_saved_signatures_version_and_operational_data(string $form): void
    {
        $custody = $this->fixture($form);
        $before = $this->operationalState();
        $version = $custody->request->currentVersion;
        $this->html($custody, $form);
        $signatures = [$version->borrowerSignature, $version->approvalSteps->firstWhere('decision', 'APPROVED')->signatureSnapshot];
        if ($form === 'GATE_PASS') {
            $signatures[] = $custody->gatePass->preparedVerifierSignature;
        }
        $this->assertCount($form === 'GATE_PASS' ? 3 : 2, $signatures);
        $rawHtml = $this->renderHtml($custody, $form);
        foreach ($signatures as $signature) {
            $this->assertStringContainsString(base64_encode(app(ProtectedFileService::class)->bytes($signature->file)), $rawHtml);
        }
        $document = app(DocumentService::class)->conditionalForm($custody, $form)->fresh('file');
        $bytes = app(ProtectedFileService::class)->bytes($document->file);
        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertStringEndsWith('%%EOF', trim($bytes));
        $this->assertSame(hash('sha256', $bytes), $document->sha256);
        $this->assertSame($document->sha256, $document->file->sha256);
        $this->assertSame('CONTROLLED_DOCUMENT', $document->file->classification);
        $this->assertSame('local', $document->file->disk);
        $this->assertSame($version->id, $document->request_version_id);
        $this->assertSame($custody::class, $document->subject_type);
        $this->assertSame($custody->id, $document->subject_id);
        $this->assertSame($form, $document->document_type);
        $this->assertSame('FINAL', $document->status);
        $this->assertSame(1, GeneratedDocument::count());
        $this->assertSame($before, $this->operationalState());
    }

    #[DataProvider('forms')]
    public function test_preapproval_generation_is_denied_without_artifacts(string $form): void
    {
        $custody = $this->fixture($form);
        $custody->request->update(['status' => RequestStatus::UnderSpmu, 'final_approved_at' => null]);
        $this->assertGenerationDenied($custody->fresh(), $form, 'document');
    }

    #[DataProvider('forms')]
    public function test_inapplicable_conditional_form_is_denied_without_artifacts(string $form): void
    {
        $custody = $this->fixture($form === 'GATE_PASS' ? 'LAUNDRY_FORM' : 'GATE_PASS');
        $this->assertGenerationDenied($custody, $form, 'document');
    }

    #[DataProvider('forms')]
    public function test_unknown_loaded_affiliation_keeps_existing_controlled_copy_valid(string $form): void
    {
        $custody = $this->fixture($form);
        $document = app(DocumentService::class)->conditionalForm($custody, $form)->fresh('file');
        $documentAttributes = $document->getAttributes();
        $bytes = app(ProtectedFileService::class)->bytes($document->file);
        $version = $custody->request->currentVersion;
        $version->setRawAttributes(array_replace($version->getAttributes(), ['affiliation_type' => 'UNKNOWN']));
        $this->assertGenerationDenied($custody, $form, 'affiliation');
        $this->assertSame($documentAttributes, $document->fresh()->getAttributes());
        $this->assertSame($bytes, app(ProtectedFileService::class)->bytes($document->file));
    }

    #[DataProvider('forms')]
    public function test_profile_edits_do_not_rewrite_existing_file_and_access_remains_scoped(string $form): void
    {
        $custody = $this->fixture($form);
        $document = app(DocumentService::class)->conditionalForm($custody, $form)->fresh('file');
        $attributes = $document->getAttributes();
        $fileAttributes = $document->file->getAttributes();
        $bytes = app(ProtectedFileService::class)->bytes($document->file);
        $owner = $custody->request->borrower;
        $owner->borrowerRegistration->update(['company_name' => 'Later Profile Agency']);
        $this->actingAs($owner)->withSession(['active_workspace' => 'BORROWER'])
            ->get(route('documents.download', $document))->assertOk()->assertStreamedContent($bytes);
        $this->actingAs($this->employee())->withSession(['active_workspace' => 'BORROWER'])
            ->get(route('documents.view', $document))->assertForbidden();
        $sao = User::where('access_classification', AccessClassification::SpmuOfficer)->firstOrFail();
        $this->actingAs($sao)->withSession(['active_workspace' => 'SPMU'])
            ->get(route('documents.view', $document))->assertOk()->assertStreamedContent($bytes);
        $head = User::where('access_classification', AccessClassification::SpmuHead)->firstOrFail();
        $this->actingAs($head)->withSession(['active_workspace' => 'SPMU'])
            ->get(route('documents.view', $document))->assertOk()->assertStreamedContent($bytes);
        $this->assertSame($attributes, $document->fresh()->getAttributes());
        $this->assertSame($fileAttributes, $document->file->fresh()->getAttributes());
        $this->assertSame(1, GeneratedDocument::count());
    }

    #[DataProvider('forms')]
    public function test_physical_worker_guard_fields_and_records_are_preserved_without_prefilling(string $form): void
    {
        $custody = $this->fixture($form);
        if ($form === 'GATE_PASS') {
            $custody->gatePass->update(['guard_name' => 'Recorded Physical Guard', 'guard_signed_at' => now()->subDay()]);
        } else {
            $job = LaundryJob::create(['custody_transaction_id' => $custody->id, 'status' => 'FOR_LAUNDRY',
                'worker_name' => 'Recorded Physical Laundry Worker', 'worker_received_at' => now()->subDay(),
                'worker_completed_at' => now(), 'worker_remarks' => 'Recorded actual form remarks']);
            LaundryJobLine::create(['laundry_job_id' => $job->id, 'custody_line_id' => $custody->lines->sole()->id,
                'issued_quantity' => 1, 'received_quantity' => 1, 'issue_type' => 'FINE', 'affected_quantity' => 0,
                'completed_quantity' => 1, 'remarks' => 'Recorded actual condition']);
        }
        $before = $this->operationalState();
        $xpath = $this->html($custody->fresh(), $form);
        if ($form === 'GATE_PASS') {
            $this->assertSame('', trim($xpath->query('//table[@class="gp-release-block"]//div[contains(@class,"gp-handwritten-line")]')->item(0)->textContent));
            foreach ($xpath->query('//div[@class="gp-release-meta"]/span') as $field) {
                $this->assertSame('', trim($field->textContent));
            }
            $this->assertStringNotContainsString('Recorded Physical Guard', $xpath->document->textContent);
        } else {
            foreach ($xpath->query('//table[@class="lf-signatures"]/tbody/tr') as $row) {
                $cells = $xpath->query('./td', $row);
                $this->assertSame('', trim($cells->item(3)->textContent));
                $this->assertSame('', trim($cells->item(4)->textContent));
            }
            $this->assertSame('', trim($xpath->query('//tr[@class="lf-item-row"]/td[5]')->item(0)->textContent));
            $this->assertStringNotContainsString('Recorded Physical Laundry Worker', $xpath->document->textContent);
        }
        app(DocumentService::class)->conditionalForm($custody->fresh(), $form);
        $this->assertSame($before, $this->operationalState());
    }

    private function fixture(string $form, bool $external = true, ?string $company = 'Reviewed Agency'): CustodyTransaction
    {
        return ApprovedConditionalDocumentFixture::create($this, $form, $external, $company);
    }

    private function employee(): User
    {
        return User::where('access_classification', AccessClassification::BorrowerOnly)->whereNotNull('organizational_unit_id')->firstOrFail();
    }

    private function renderHtml(CustodyTransaction $custody, string $form): string
    {
        $custody->loadMissing('request.borrower.organizationalUnit', 'request.currentVersion.borrowerSignature.file',
            'request.currentVersion.approvalSteps.approver', 'request.currentVersion.approvalSteps.signatureSnapshot.file',
            'lines.requestItem.inventoryItem', 'gatePass.preparedVerifier', 'gatePass.preparedVerifierSignature.file',
            'gatePass.approver', 'gatePass.approverSignature.file');

        return (new ReflectionMethod(DocumentService::class, $form === 'LAUNDRY_FORM' ? 'laundryFormHtml' : 'gatePassHtml'))
            ->invoke(app(DocumentService::class), $custody);
    }

    private function html(CustodyTransaction $custody, string $form): DOMXPath
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$this->renderHtml($custody, $form));

        return new DOMXPath($dom);
    }

    private function affiliation(DOMXPath $xpath, string $form): string
    {
        $selector = $form === 'LAUNDRY_FORM'
            ? '//table[@class="lf-request-meta"]//tr/td[2]'
            : '//table[@class="gp-bearer-block"]//div[@class="gp-signature-affiliation"]';

        return trim($xpath->query($selector)->item(0)?->textContent ?? '');
    }

    private function operationalState(): array
    {
        $tables = ['borrowing_requests', 'request_versions', 'approval_steps', 'signature_snapshots', 'inventory_items',
            'allocations', 'custody_transactions', 'custody_lines', 'gate_passes', 'laundry_jobs', 'laundry_job_lines',
            'audit_events', 'notification_events'];
        $state = [];
        foreach ($tables as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }

        return $state;
    }

    private function assertGenerationDenied(CustodyTransaction $custody, string $form, string $field): void
    {
        $before = $this->operationalState();
        $files = StoredFile::count();
        $documents = GeneratedDocument::count();
        $paths = Storage::disk('local')->allFiles();
        try {
            app(DocumentService::class)->conditionalForm($custody, $form);
            $this->fail('The controlled document must be refused.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
        $this->assertSame($documents, GeneratedDocument::count());
        $this->assertSame($files, StoredFile::count());
        $this->assertSame($paths, Storage::disk('local')->allFiles());
        $this->assertSame($before, $this->operationalState());
    }
}
