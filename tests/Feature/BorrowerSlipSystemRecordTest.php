<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Enums\RequestStatus;
use App\Models\Allocation;
use App\Models\ApprovalStep;
use App\Models\BorrowingRequest;
use App\Models\CustodyLine;
use App\Models\CustodyTransaction;
use App\Models\EvidenceSubmission;
use App\Models\GeneratedDocument;
use App\Models\InventoryItem;
use App\Models\LaundryJob;
use App\Models\RequestItem;
use App\Models\ReturnTransaction;
use App\Models\SignatureSnapshot;
use App\Models\StoredFile;
use App\Models\User;
use App\Models\UserSignature;
use App\Services\DocumentService;
use App\Services\ProtectedFileService;
use App\Services\SignatureService;
use Database\Seeders\DatabaseSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\EvidenceFixtures;
use Tests\TestCase;

class BorrowerSlipSystemRecordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 28)->setTime(10, 0));
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
    }

    public function test_planned_pickup_never_appears_as_an_actual_release_or_return(): void
    {
        [$custody, $document] = $this->fixture();
        $cells = $this->cells($document);

        $this->assertSame([''], $cells['c-release-date']);
        $this->assertSame([''], $cells['c-release-time']);
        $this->assertSame([''], $cells['c-return-date']);
        $this->assertSame([''], $cells['c-remarks']);
        $this->asBorrower($custody)->get(route('documents.preview', $document))
            ->assertOk()->assertDontSeeText('Updated record');
    }

    public function test_record_uses_actual_release_in_manila_and_keeps_return_and_wet_signature_cells_blank(): void
    {
        [$custody, $document] = $this->fixture();
        $this->release($custody);
        $cells = $this->cells($document);

        $this->assertSame(['10/22/2026'], $cells['c-release-date']);
        $this->assertSame(['11:42 AM'], $cells['c-release-time']);
        $this->assertSame([''], $cells['c-return-date']);
        $this->assertSame([''], $cells['c-remarks']);
        $html = app(DocumentService::class)->borrowerSlipRecordHtml($document);
        $this->assertStringNotContainsString('Pending', strip_tags($html));
        $xpath = $this->xpath($html);
        $signatureCells = $xpath->query('//tr[@class="signature-row"]/td');
        $this->assertSame(2, $xpath->query('//div[@class="esign"]')->length);
        foreach ([3, 4, 6, 7, 8] as $index) {
            $this->assertSame('', trim($signatureCells->item($index)->textContent));
            $this->assertSame(0, $signatureCells->item($index)->getElementsByTagName('img')->length);
        }
    }

    public function test_filled_slip_stream_allows_same_origin_preview_while_the_page_remains_protected(): void
    {
        [$custody, $document] = $this->fixture();
        $this->release($custody);
        $officer = User::where('access_classification', AccessClassification::SpmuOfficer)->firstOrFail();
        $this->actingAs($officer)->withSession(['active_workspace' => 'SPMU']);

        $this->get(route('documents.preview', $document))->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY');
        $response = $this->get(route('documents.record', $document))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString("frame-ancestors 'self'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith('%PDF-', $response->streamedContent());
    }

    public function test_complete_return_uses_the_recorded_physical_date_and_excludes_internal_notes(): void
    {
        [$custody, $document] = $this->fixture();
        $this->release($custody);
        $this->inspect($custody, false, '2026-10-23');
        $cells = $this->cells($document);

        $this->assertSame(['10/23/2026'], $cells['c-return-date']);
        $this->assertSame(['Good condition'], $cells['c-remarks']);
        $html = app(DocumentService::class)->borrowerSlipRecordHtml($document);
        $this->assertStringNotContainsString('PRIVATE AUDIT NOTE', $html);
        $this->assertStringNotContainsString('10/28/2026</td>', $html);
    }

    public function test_incomplete_quantity_does_not_publish_a_final_return_date_or_findings(): void
    {
        [$custody, $document] = $this->fixture();
        $this->release($custody);
        $this->inspect($custody, false, '2026-10-23', quantity: 1);

        $this->assertSame([''], $this->cells($document)['c-return-date']);
        $this->assertSame([''], $this->cells($document)['c-remarks']);
    }

    public function test_mixed_items_remain_blank_until_accomplished_form_is_uploaded_and_return_encoded(): void
    {
        [$custody, $document] = $this->fixture(mixed: true);
        $this->release($custody);
        $this->inspect($custody, false, '2026-10-24');
        $this->assertSame(['', ''], $this->cells($document)['c-return-date']);

        // Even counters/inspection records cannot substitute for the required form.
        $this->inspect($custody, true, '2026-10-23');
        $this->assertSame(['', ''], $this->cells($document)['c-return-date']);
        $job = $this->laundryForm($custody, 'FOR_LAUNDRY');
        $this->assertSame(['', ''], $this->cells($document)['c-return-date']);
        $job->update(['status' => 'LAUNDRY_COMPLETED']);

        $this->assertSame(['10/24/2026', '10/24/2026'], $this->cells($document)['c-return-date']);
        $this->assertSame(['Good condition', 'Good condition'], $this->cells($document)['c-remarks']);
    }

    public function test_one_transaction_date_and_recorded_adverse_findings_are_used_without_reusing_signatures(): void
    {
        [$custody, $document] = $this->fixture(mixed: true);
        $this->release($custody);
        $this->inspect($custody, false, '2026-10-24', condition: 'DAMAGED');
        $this->inspect($custody, true, '2026-10-23');
        $this->laundryForm($custody);
        $cells = $this->cells($document);

        $this->assertSame(['10/24/2026', '10/24/2026'], $cells['c-return-date']);
        $this->assertSame(['2 damaged', 'Good condition'], $cells['c-remarks']);
        $this->assertStringNotContainsString('PRIVATE AUDIT NOTE', app(DocumentService::class)->borrowerSlipRecordHtml($document));
    }

    public function test_reading_current_record_preserves_original_file_inventory_documents_and_audit(): void
    {
        [$custody, $document] = $this->fixture();
        $this->release($custody);
        $this->inspect($custody, false, '2026-10-23');
        $before = $this->state($document);

        $this->asBorrower($custody);
        for ($i = 0; $i < 2; $i++) {
            $this->get(route('documents.preview', $document))->assertOk()
                ->assertDontSeeText('Updated record')->assertDontSeeText('Original pickup copy')
                ->assertDontSeeText('no additional printout is required')
                ->assertSee(route('documents.record', $document, false), false);
            $this->get(route('documents.record', $document))->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');
        }
        $this->get(route('documents.preview', ['document' => $document, 'copy' => 'original']))
            ->assertOk()->assertSee(route('documents.record', $document, false), false);
        $original = $this->get(route('documents.view', $document))->assertOk()->streamedContent();
        $this->assertSame($before['bytes'], $original);
        $this->assertSame($before, $this->state($document));
    }

    public function test_original_pickup_copy_is_in_head_document_history_and_not_sao_or_borrower_preview(): void
    {
        [$custody, $document] = $this->fixture();
        $this->release($custody);
        $head = User::where('access_classification', AccessClassification::SpmuHead)->firstOrFail();
        $officer = User::where('access_classification', AccessClassification::SpmuOfficer)->firstOrFail();
        $before = $this->state($document);

        $this->actingAs($head)->withSession(['active_workspace' => 'SPMU'])
            ->get(route('requests.show', $custody->request))->assertOk()
            ->assertSee('data-document-history', false)
            ->assertSee(route('documents.original', $document), false);
        $this->get(route('documents.original', $document))->assertOk()
            ->assertSeeText('Borrower Slip (Original pickup copy)')
            ->assertSee(route('documents.view', $document, false), false)
            ->assertDontSee(route('documents.record', $document, false), false);

        foreach ([$officer, $custody->borrower] as $user) {
            $this->actingAs($user)->withSession(['active_workspace' => $user->primaryWorkspace()])
                ->get(route('requests.show', $custody->request))->assertOk()
                ->assertDontSee(route('documents.original', $document), false);
            $this->get(route('documents.original', $document))->assertForbidden();
            $this->get(route('documents.preview', ['document' => $document, 'copy' => 'original']))
                ->assertOk()->assertSee(route('documents.record', $document, false), false)
                ->assertDontSeeText('Original pickup copy')->assertDontSeeText('Updated record');
        }

        $this->assertSame($before, $this->state($document));
    }

    public function test_sao_can_open_filled_slip_from_completed_return_without_history_controls(): void
    {
        [$custody, $document] = $this->fixture();
        $this->release($custody);
        $this->inspect($custody, false, '2026-10-23');
        $custody->update(['status' => 'CLOSED']);
        $officer = User::where('access_classification', AccessClassification::SpmuOfficer)->firstOrFail();

        $this->actingAs($officer)->withSession(['active_workspace' => 'SPMU'])
            ->get(route('custody.return.show', $custody))->assertOk()
            ->assertSeeText('Completed')->assertSeeText('Borrower Slip')
            ->assertSee(route('documents.preview', $document), false)
            ->assertDontSee(route('documents.original', $document), false);
        $this->get(route('documents.preview', $document))->assertOk()
            ->assertSee(route('documents.record', $document, false), false)
            ->assertDontSeeText('Updated record')->assertDontSeeText('Original pickup copy');
        $this->assertSame(['10/23/2026'], $this->cells($document)['c-return-date']);
    }

    public function test_system_record_preserves_the_document_signed_version_and_snapshot_names(): void
    {
        [$custody, $document] = $this->fixture();
        $this->release($custody);
        $borrowerName = $document->version->borrowerSignature->signer_name;
        $custody->borrower->update(['full_name' => 'LATER PROFILE NAME']);
        $head = User::where('access_classification', AccessClassification::SpmuHead)->firstOrFail();
        $head->update(['full_name' => 'LATER APPROVER NAME']);
        $newVersion = $document->version->replicate();
        $newVersion->fill(['version_no' => 2, 'purpose_event' => 'UNRELATED NEW VERSION',
            'borrower_signature_snapshot_id' => null, 'signed_at' => null, 'submitted_at' => null]);
        $newVersion->save();
        $custody->request->update(['current_version_no' => 2]);
        $html = app(DocumentService::class)->borrowerSlipRecordHtml($document->fresh());

        $this->assertStringContainsString('SYSTEM RECORD FIXTURE', $html);
        $this->assertStringContainsString(e($borrowerName), $html);
        $this->assertStringNotContainsString('UNRELATED NEW VERSION', $html);
        $this->assertStringNotContainsString('LATER PROFILE NAME', $html);
        $this->assertStringNotContainsString('LATER APPROVER NAME', $html);
    }

    public function test_record_access_checks_owner_subject_and_invalidated_document(): void
    {
        [$custody, $document] = $this->fixture();
        $this->release($custody);
        $other = User::factory()->create();
        $this->actingAs($other)->withSession(['active_workspace' => 'BORROWER'])
            ->get(route('documents.record', $document))->assertForbidden();
        $this->asBorrower($custody);
        $document->update(['status' => 'INVALIDATED']);
        $this->get(route('documents.record', $document))->assertGone();
        $document->update(['status' => 'FINAL', 'subject_id' => 999999]);
        $this->get(route('documents.record', $document))->assertForbidden();
        $document->update(['subject_id' => $custody->id, 'document_type' => 'GATE_PASS']);
        $this->get(route('documents.record', $document))->assertNotFound();
    }

    public function test_reopened_inspection_does_not_publish_a_final_return_date(): void
    {
        [$custody, $document] = $this->fixture();
        $this->release($custody);
        $return = $this->inspect($custody, false, '2026-10-23');
        $return->update(['status' => 'REOPENED']);
        $this->assertSame([''], $this->cells($document)['c-return-date']);
    }

    private function asBorrower(CustodyTransaction $custody): static
    {
        return $this->actingAs($custody->borrower)->withSession(['active_workspace' => 'BORROWER']);
    }

    private function cells(GeneratedDocument $document): array
    {
        $xpath = $this->xpath(app(DocumentService::class)->borrowerSlipRecordHtml($document->fresh()));
        $result = [];
        foreach (['c-release-date', 'c-release-time', 'c-return-date', 'c-remarks'] as $class) {
            $result[$class] = [];
            foreach ($xpath->query('//tr[@class="actual-item"]/td[@class="'.$class.'"]') as $cell) {
                $result[$class][] = trim($cell->textContent);
            }
        }

        return $result;
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);

        return new DOMXPath($dom);
    }

    private function state(GeneratedDocument $document): array
    {
        return [
            'bytes' => app(ProtectedFileService::class)->bytes($document->fresh('file')->file),
            'document' => $document->fresh()->getAttributes(),
            'inventory' => DB::table('inventory_items')->orderBy('id')->get()->toJson(),
            'custodies' => DB::table('custody_transactions')->orderBy('id')->get()->toJson(),
            'documents' => GeneratedDocument::count(), 'files' => StoredFile::count(),
            'storage' => Storage::disk('local')->allFiles(), 'audit' => DB::table('audit_events')->count(),
        ];
    }

    private function release(CustodyTransaction $custody): void
    {
        $custody->update(['released_at' => '2026-10-22 11:42:00', 'status' => 'RELEASED',
            'released_by_user_id' => User::where('access_classification', AccessClassification::SpmuOfficer)->value('id')]);
        $custody->lines()->update(['actual_released_quantity' => 2]);
    }

    private function inspect(CustodyTransaction $custody, bool $linen, string $date, string $condition = 'FINE', int $quantity = 2): ReturnTransaction
    {
        $return = $custody->returns()->create([
            'return_no' => 'TEST-RET-'.uniqid(), 'received_at' => $linen ? $date.' 00:00:00' : now(),
            'physical_return_date' => $linen ? null : $date, 'status' => 'INSPECTED',
            'received_by_user_id' => User::where('access_classification', AccessClassification::SpmuOfficer)->value('id'),
            'return_type' => 'NORMAL', 'remarks' => 'PRIVATE AUDIT NOTE',
        ]);
        $line = $custody->lines()->whereHas('requestItem.inventoryItem', fn ($q) => $q->where('laundry_required', $linen))->firstOrFail();
        $return->lines()->create(['custody_line_id' => $line->id, 'quantity_received' => $quantity,
            'condition_code' => $condition, 'disposition_state' => 'AVAILABLE']);
        $line->update(['returned_quantity' => $quantity]);

        return $return;
    }

    private function laundryForm(CustodyTransaction $custody, string $status = 'LAUNDRY_COMPLETED'): LaundryJob
    {
        $file = app(ProtectedFileService::class)->storeBytes(EvidenceFixtures::pdfBytes(),
            'test-laundry', 'accomplished-form.pdf', 'application/pdf', 'pdf', 'CONTROLLED_DOCUMENT');
        $form = GeneratedDocument::create(['document_type' => 'LAUNDRY_FORM', 'stored_file_id' => $file->id,
            'subject_type' => CustodyTransaction::class, 'subject_id' => $custody->id,
            'request_version_id' => $custody->request_version_id, 'document_no' => 'TEST-LAUNDRY-'.uniqid(),
            'sha256' => $file->sha256, 'status' => 'FINAL', 'generated_at' => now()]);
        $evidence = EvidenceSubmission::create(['generated_document_id' => $form->id, 'stored_file_id' => $file->id,
            'borrower_user_id' => $custody->borrower_user_id, 'upload_mode' => 'SPMU_ACTION_OFFICER',
            'uploaded_by_user_id' => User::where('access_classification', AccessClassification::SpmuOfficer)->value('id'),
            'verified_by_user_id' => User::where('access_classification', AccessClassification::SpmuOfficer)->value('id'),
            'verification_status' => 'VERIFIED', 'submitted_at' => now(), 'verified_at' => now()]);

        return LaundryJob::create(['custody_transaction_id' => $custody->id, 'status' => $status,
            'generated_document_id' => $form->id, 'latest_evidence_submission_id' => $evidence->id,
            'worker_received_at' => '2026-10-23', 'form_verified_at' => now(),
            'form_verified_by_user_id' => User::where('access_classification', AccessClassification::SpmuOfficer)->value('id')]);
    }

    private function snapshot(User $user, string $purpose, string $role): SignatureSnapshot
    {
        $file = app(ProtectedFileService::class)->storeBytes(EvidenceFixtures::signatureBytes($role),
            'test-signatures', 'signature.png', 'image/png', 'png', 'SIGNATURE', $user->id);
        UserSignature::create(['user_id' => $user->id, 'stored_file_id' => $file->id,
            'effective_from' => now()->subMinute(), 'status' => 'ACTIVE']);
        $this->actingAs($user);

        return app(SignatureService::class)->snapshot($user, $purpose, $role);
    }

    private function fixture(bool $mixed = false): array
    {
        $borrower = User::where('access_classification', AccessClassification::BorrowerOnly)->firstOrFail();
        $head = User::where('access_classification', AccessClassification::SpmuHead)->firstOrFail();
        $request = BorrowingRequest::create(['request_no' => 'TEST-SLIP-'.uniqid(),
            'borrower_user_id' => $borrower->id, 'accountable_unit_id' => $borrower->organizational_unit_id,
            'current_version_no' => 1, 'status' => RequestStatus::ApprovedReadyForRelease,
            'final_approved_at' => '2026-10-21 09:00:00']);
        $version = $request->versions()->create(['version_no' => 1, 'purpose_event' => 'SYSTEM RECORD FIXTURE',
            'location' => 'Synthetic venue', 'schedule_date' => '2026-10-23', 'return_date' => '2026-10-24',
            'needed_from' => '2026-10-23', 'return_due_at' => '2026-10-24 17:00:00',
            'created_by_user_id' => $borrower->id, 'affiliation_type' => 'EMPLOYEE']);
        $snapshot = $this->snapshot($borrower, 'BORROWER_REQUEST_CERTIFICATION', 'BORROWER');
        $version->update(['borrower_signature_snapshot_id' => $snapshot->id, 'signed_at' => '2026-10-21 08:00:00']);
        $approval = $this->snapshot($head, 'SPMU_HEAD_APPROVAL', 'SPMU_HEAD');
        ApprovalStep::create(['request_version_id' => $version->id, 'stage_code' => 'SPMU',
            'sequence_no' => 2, 'decision' => 'APPROVED', 'approver_user_id' => $head->id,
            'signature_snapshot_id' => $approval->id, 'decided_at' => '2026-10-21 09:00:00']);
        $custody = CustodyTransaction::create(['custody_no' => 'TEST-CUS-'.uniqid(), 'request_id' => $request->id,
            'request_version_id' => $version->id, 'borrower_user_id' => $borrower->id,
            'status' => 'PREPARING_RELEASE', 'due_at' => $version->return_due_at,
            'scheduled_release_at' => '2026-10-22 08:00:00', 'pickup_expires_at' => '2026-10-22 17:00:00']);
        foreach ($mixed ? [false, true] : [false] as $linen) {
            $item = InventoryItem::with('unit')->where('active', true)->where('borrowable', true)
                ->where('laundry_required', $linen)->firstOrFail();
            $requestItem = RequestItem::create(['request_version_id' => $version->id,
                'inventory_item_id' => $item->id, 'description_snapshot' => $item->unique_description,
                'unit_snapshot' => $item->unit->unit_name, 'requested_quantity' => 2,
                'approved_quantity' => 2, 'use_location' => 'ON_CAMPUS']);
            $allocation = Allocation::create(['request_item_id' => $requestItem->id,
                'period_start' => $version->needed_from, 'period_end' => $version->return_due_at,
                'allocated_quantity' => 2, 'released_quantity' => 0, 'restored_quantity' => 0,
                'status' => 'ACTIVE', 'allocated_at' => now()]);
            CustodyLine::create(['custody_transaction_id' => $custody->id,
                'request_item_id' => $requestItem->id, 'allocation_id' => $allocation->id,
                'approved_quantity' => 2, 'quantity_to_receive' => 2, 'actual_released_quantity' => 0,
                'returned_quantity' => 0, 'item_status' => 'PENDING']);
        }
        $document = app(DocumentService::class)->borrowerSlip($custody->fresh());

        return [$custody->fresh(), $document->fresh(['file', 'version.borrowerSignature'])];
    }
}
