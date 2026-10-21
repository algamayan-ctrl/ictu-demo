<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Enums\RequestStatus;
use App\Models\ApprovalStep;
use App\Models\AuditEvent;
use App\Models\BorrowingRequest;
use App\Models\GeneratedDocument;
use App\Models\InventoryItem;
use App\Models\RequestSupportingDocument;
use App\Models\StoredFile;
use App\Models\User;
use App\Models\UserSignature;
use App\Services\OperationalCalendarService;
use App\Services\ProtectedFileService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Audit Trail investigation support: who changed a record, when, the previous
 * and new values, the reason, and whether earlier versions remain retrievable
 * by authorized personnel only.
 */
class AuditTrailInvestigationTest extends TestCase
{
    use RefreshDatabase;

    private User $borrower;

    private User $officer;

    private User $head;

    private User $ictu;

    private InventoryItem $borrowableItem;

    protected function setUp(): void
    {
        parent::setUp();

        // These tests change data; they must only ever run on the isolated test database.
        /*
         * Tests\TestCase has already refused any database that is not provably
         * disposable, before RefreshDatabase rebuilt it. Asserted again here so
         * the requirement stays visible beside tests that rewrite audit history.
         */
        $this->assertDisposableTestDatabase();

        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');

        /*
         * A fixed Monday, 09:00. requestPayload() derives its dates from the
         * clock (pickup two days on, Items Needed From the day after, return
         * the day after that), and the operational calendar closes weekends.
         * From a Monday those land on Wednesday, Thursday and Friday. Started
         * from the real date instead, the return date fell on a Saturday
         * whenever the suite ran on a Tuesday or Wednesday and the request
         * was correctly refused as "SPMU is closed".
         */
        $this->travelTo(Carbon::create(2026, 10, 12, 9, 0, 0, config('app.timezone')));

        $this->borrower = $this->classificationUser(AccessClassification::BorrowerOnly);
        $this->officer = $this->classificationUser(AccessClassification::SpmuOfficer);
        $this->head = $this->classificationUser(AccessClassification::SpmuHead);
        $this->ictu = $this->classificationUser(AccessClassification::IctuMaintainer);
        $this->borrowableItem = InventoryItem::query()
            ->where('active', true)
            ->where('borrowable', true)
            ->where('off_campus_allowed', true)
            ->firstOrFail();

        foreach ([$this->borrower, $this->officer, $this->head] as $user) {
            $this->registerSignature($user);
        }
    }

    public function test_inventory_renames_keep_separate_before_and_after_values_and_the_actor_snapshot(): void
    {
        $item = $this->createInventoryItem('Investigation Chair Blue', 50);

        $this->as($this->head)
            ->put(route('inventory.update', $item), $this->inventoryPayload('Investigation Chair White') + ['change_reason' => 'Supplier relabelled the set.'])
            ->assertSessionHasNoErrors();
        $this->as($this->head)
            ->put(route('inventory.update', $item), $this->inventoryPayload('Investigation Chair Green') + ['change_reason' => 'Second correction.'])
            ->assertSessionHasNoErrors();

        $updates = AuditEvent::query()
            ->where('action_code', 'INVENTORY_ITEM_UPDATED')
            ->where('record_id', $item->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $updates);
        $this->assertSame('Investigation Chair Blue', $updates[0]->before_json['unique_description']);
        $this->assertSame('Investigation Chair White', $updates[0]->after_json['unique_description']);
        $this->assertSame('Supplier relabelled the set.', $updates[0]->reason);
        $this->assertSame('Investigation Chair White', $updates[1]->before_json['unique_description']);
        $this->assertSame('Investigation Chair Green', $updates[1]->after_json['unique_description']);
        $this->assertSame('Second correction.', $updates[1]->reason);

        // The actor's name and role are stored with each new event.
        foreach ($updates as $event) {
            $this->assertSame($this->head->id, $event->actor_user_id);
            $this->assertSame($this->head->full_name, $event->actor_name);
            $this->assertSame(AccessClassification::SpmuHead->value, $event->actor_role);
            $this->assertNotNull($event->occurred_at);
        }

        // A later rename of the account does not change how the earlier events read.
        $this->head->forceFill(['full_name' => 'Renamed Head Account'])->save();
        $this->assertSame('SPMU Head Demo', $updates[0]->fresh()->actor_name);

        // "View Full History" lists the record's events oldest first.
        $response = $this->as($this->head->fresh())
            ->get(route('reports.audit', ['record_type' => 'InventoryItem', 'record_id' => $item->id]))
            ->assertOk()
            ->assertSee('Full history for')
            ->assertSee('Investigation Chair Green')
            ->assertSee('SPMU Head Demo')
            ->assertSee('at the time');

        $history = $response->viewData('events');
        $this->assertSame(3, $history->total());
        $this->assertSame(
            ['INVENTORY_ITEM_CREATED', 'INVENTORY_ITEM_UPDATED', 'INVENTORY_ITEM_UPDATED'],
            $history->getCollection()->pluck('action_code')->all()
        );
    }

    public function test_operational_approval_audit_prioritizes_the_request_and_separates_technical_evidence(): void
    {
        $request = $this->saveDraftWithLetter('audit-approval-context.pdf');
        $version = $request->currentVersion;
        $step = ApprovalStep::query()->create([
            'request_version_id' => $version->id,
            'stage_code' => 'SPMU',
            'sequence_no' => 1,
        ]);

        AuditEvent::query()->create([
            'actor_user_id' => $this->head->id,
            'actor_name' => $this->head->full_name,
            'actor_role' => AccessClassification::SpmuHead->value,
            'action_code' => 'SPMU_REQUEST_DECISION',
            'record_type' => ApprovalStep::class,
            'record_id' => $step->id,
            'occurred_at' => now(),
            'origin_ip' => '198.51.100.14',
            'correlation_id' => (string) Str::uuid(),
            'after_json' => [
                'decision' => 'APPROVED',
                'reservation_created' => true,
                'request_id' => $request->id,
                'request_version_id' => $version->id,
                'signer_user_id' => $this->head->id,
                'signature_snapshot_id' => 75,
                'signed_at' => now()->toIso8601String(),
            ],
        ]);

        $this->as($this->head)
            ->get(route('reports.audit'))
            ->assertOk()
            ->assertSee('Borrowing Request Approved')
            ->assertSee('Approval completed for '.$request->request_no.'. Items were reserved for release.')
            ->assertSee('Borrowing Request · '.$request->request_no)
            ->assertSee('Operational Changes')
            ->assertSee('Technical verification details')
            ->assertSee('data-audit-operational-changes', false)
            ->assertSee('data-audit-technical-verification', false)
            ->assertDontSee('Spmu Request Decision')
            ->assertDontSee('Approval Step #')
            ->assertDontSee('Audit event recorded.');

        $this->as($this->head)
            ->get(route('reports.audit', ['record_type' => 'ApprovalStep', 'record_id' => $step->id]))
            ->assertOk()
            ->assertSee('Full history for')
            ->assertSee('Borrowing Request · '.$request->request_no)
            ->assertDontSee('Full history of Approval Step #');
    }

    public function test_operational_audit_uses_clear_labels_across_its_main_workflows(): void
    {
        $request = $this->saveDraftWithLetter('audit-workflow-labels.pdf');
        $events = [
            'ITEMS_RELEASED',
            'LAUNDRY_RETURN_RECORDED_FROM_FORM',
            'PROPERTY_ACCOUNTABILITY_COMPLIANCE_VERIFIED',
            'INVENTORY_ADJUSTMENT_RECORDED',
            'OPERATIONAL_DATE_EXCEPTION_SAVED',
        ];

        foreach ($events as $actionCode) {
            AuditEvent::query()->create([
                'actor_user_id' => $this->head->id,
                'actor_name' => $this->head->full_name,
                'actor_role' => AccessClassification::SpmuHead->value,
                'action_code' => $actionCode,
                'record_type' => $request::class,
                'record_id' => $request->id,
                'occurred_at' => now(),
                'correlation_id' => (string) Str::uuid(),
                'after_json' => ['request_no' => $request->request_no],
            ]);
        }

        $this->as($this->head)
            ->get(route('reports.audit', ['per_page' => 14]))
            ->assertOk()
            ->assertSee('Items Released to Borrower')
            ->assertSee('Laundry Return Recorded')
            ->assertSee('Accountability Compliance Verified')
            ->assertSee('Inventory Adjustment Recorded')
            ->assertSee('Operating Date Exception Saved')
            ->assertDontSee('Audit event recorded.');
    }

    public function test_an_inventory_change_without_a_reason_is_rejected_and_leaves_no_audit_event(): void
    {
        $item = $this->createInventoryItem('Investigation Table', 10);
        $eventsBefore = AuditEvent::query()->count();

        $this->as($this->head)
            ->put(route('inventory.update', $item), $this->inventoryPayload('Investigation Table Renamed'))
            ->assertSessionHasErrors('change_reason');

        $this->assertSame('Investigation Table', $item->fresh()->unique_description);
        $this->assertSame($eventsBefore, AuditEvent::query()->count());
    }

    public function test_a_deleted_inventory_item_keeps_an_accessible_snapshot_and_is_marked_deleted(): void
    {
        $item = $this->createInventoryItem('Investigation Duplicate Entry', 0);
        $itemId = $item->id;

        $this->as($this->head)
            ->delete(route('inventory.destroy', $item), [
                'confirm_item_name' => 'Investigation Duplicate Entry',
                'delete_reason' => 'Duplicate encoding.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('inventory_items', ['id' => $itemId]);

        $event = AuditEvent::query()
            ->where('action_code', 'INVENTORY_ITEM_DELETED')
            ->where('record_id', $itemId)
            ->firstOrFail();

        $this->assertSame('Investigation Duplicate Entry', $event->before_json['unique_description']);
        $this->assertSame('Duplicate encoding.', $event->reason);

        $this->as($this->head)
            ->get(route('reports.audit', ['q' => 'Investigation Duplicate Entry']))
            ->assertOk()
            ->assertSee('Investigation Duplicate Entry')
            ->assertSee('Deleted record')
            ->assertSee('Preserved snapshot of the deleted record')
            ->assertSee('Duplicate encoding.');

        // There is deliberately no restore action.
        $this->assertEmpty(
            collect(app('router')->getRoutes())->filter(fn ($route) => str_contains($route->uri(), 'restore'))
        );
    }

    public function test_document_upload_and_replacement_are_audited_and_every_version_stays_retrievable(): void
    {
        $request = $this->saveDraftWithLetter('letter-original.pdf');

        $this->as($this->borrower)
            ->put(route('requests.update', $request), $this->requestPayload('draft', 'letter-corrected.pdf'))
            ->assertSessionHasNoErrors();

        $documents = RequestSupportingDocument::query()
            ->where('request_id', $request->id)
            ->orderBy('version_no')
            ->with('file')
            ->get();

        $this->assertCount(2, $documents);
        [$original, $replacement] = $documents;

        $this->assertFalse($original->is_current);
        $this->assertNotNull($original->superseded_at);
        $this->assertTrue($replacement->is_current);
        $this->assertSame('letter-original.pdf', $original->file->original_name);
        $this->assertSame('letter-corrected.pdf', $replacement->file->original_name);

        // Both physical files are kept, each with its own integrity hash.
        foreach ($documents as $document) {
            Storage::disk('local')->assertExists($document->file->storage_path);
            $this->assertSame(64, strlen((string) $document->file->sha256));
            $this->assertSame($this->borrower->id, $document->uploaded_by_user_id);
        }

        $uploaded = AuditEvent::query()->where('action_code', 'REQUEST_SUPPORTING_DOCUMENT_UPLOADED')->sole();
        $replaced = AuditEvent::query()->where('action_code', 'REQUEST_SUPPORTING_DOCUMENT_REPLACED')->sole();

        foreach ([$uploaded, $replaced] as $event) {
            $this->assertSame(BorrowingRequest::class, $event->record_type);
            $this->assertSame($request->id, (int) $event->record_id);
            $this->assertSame($this->borrower->id, $event->actor_user_id);
            $this->assertSame($this->borrower->full_name, $event->actor_name);
            $this->assertSame(AccessClassification::BorrowerOnly->value, $event->actor_role);
        }

        $this->assertNull($uploaded->before_json);
        $this->assertSame('letter-original.pdf', $uploaded->after_json['original_filename']);
        $this->assertSame(1, $uploaded->after_json['document_version']);

        // No reason is collected for a replacement, so none is recorded or invented.
        $this->assertNull($uploaded->reason);
        $this->assertNull($replaced->reason);
        $this->assertSame($original->id, $replaced->before_json['document_record_no']);
        $this->assertSame(1, $replaced->before_json['document_version']);
        $this->assertSame('letter-original.pdf', $replaced->before_json['original_filename']);
        $this->assertSame($original->file->sha256, $replaced->before_json['file_sha256']);
        $this->assertSame($replacement->id, $replaced->after_json['document_record_no']);
        $this->assertSame(2, $replaced->after_json['document_version']);
        $this->assertSame('letter-corrected.pdf', $replaced->after_json['original_filename']);
        $this->assertSame($request->request_no, $replaced->after_json['request_no']);

        // Only safe metadata is stored: never the document contents.
        $this->assertStringNotContainsString('%PDF', json_encode([$replaced->before_json, $replaced->after_json]));

        // Authorized readers can open both versions through the direct URL.
        foreach ([$this->head, $this->officer, $this->borrower] as $reader) {
            foreach ($documents as $document) {
                $this->assertSame(200, $this->as($reader)->get(route('files.show', $document->file))->baseResponse->getStatusCode());
            }
        }

        // The superseded version is labelled historical; viewing it changes nothing.
        $this->as($this->head)
            ->get(route('files.preview', $original->file))
            ->assertOk()
            ->assertSee('Historical version');
        $this->as($this->head)
            ->get(route('files.preview', $replacement->file))
            ->assertOk()
            ->assertDontSee('Historical version');

        $this->assertFalse($original->fresh()->is_current);
        $this->assertTrue($replacement->fresh()->is_current);
        $this->assertSame(2, RequestSupportingDocument::query()->where('request_id', $request->id)->count());
    }

    public function test_ictu_and_other_borrowers_cannot_open_confidential_documents_by_direct_url(): void
    {
        $request = $this->saveDraftWithLetter('letter-original.pdf');

        $this->as($this->borrower)
            ->put(route('requests.update', $request), $this->requestPayload('draft', 'letter-corrected.pdf'))
            ->assertSessionHasNoErrors();

        $otherBorrower = User::factory()->create([
            'access_classification' => AccessClassification::BorrowerOnly,
            'organizational_unit_id' => $this->borrower->organizational_unit_id,
            'account_status' => 'ACTIVE',
            'email_verified_at' => now(),
        ]);

        $documents = RequestSupportingDocument::query()->where('request_id', $request->id)->with('file')->get();
        $this->assertCount(2, $documents);

        // Current and superseded versions, raw file and preview page.
        foreach ($documents as $document) {
            foreach ([$this->ictu, $otherBorrower] as $outsider) {
                $this->as($outsider)->get(route('files.show', $document->file))->assertForbidden();
                $this->as($outsider)->get(route('files.preview', $document->file))->assertForbidden();
            }
        }

        // Generated borrower documents (slips, statements, ...) are closed to ICTU as well.
        $file = app(ProtectedFileService::class)->storeBytes('%PDF generated', 'generated', 'borrower-slip.pdf', 'application/pdf', 'pdf', 'GENERATED_DOCUMENT', $this->head->id);
        $generated = GeneratedDocument::query()->create([
            'stored_file_id' => $file->id,
            'request_version_id' => $request->currentVersion->id,
            'document_no' => 'GD-INVESTIGATION-1',
            'document_type' => 'BORROWER_SLIP',
            'version_no' => 1,
            'status' => 'ACTIVE',
            'generated_at' => now(),
        ]);

        foreach (['documents.download', 'documents.view', 'documents.preview'] as $route) {
            $this->as($this->ictu)->get(route($route, $generated))->assertForbidden();
            $this->as($otherBorrower)->get(route($route, $generated))->assertForbidden();
        }

        $this->assertSame(200, $this->as($this->head)->get(route('documents.download', $generated))->baseResponse->getStatusCode());
        $this->assertSame(200, $this->as($this->borrower)->get(route('documents.download', $generated))->baseResponse->getStatusCode());

        // An invalidated generated document stays closed (410) for everyone.
        $generated->update(['status' => 'SUPERSEDED']);
        $this->as($this->head)->get(route('documents.download', $generated))->assertStatus(410);
        $this->as($this->ictu)->get(route('documents.download', $generated))->assertForbidden();

        // A stale workspace value in the session grants nothing.
        $this->withSession(['active_workspace' => 'SPMU'])
            ->actingAs($this->ictu)
            ->get(route('files.show', $documents->first()->file))
            ->assertForbidden();
    }

    public function test_head_sees_the_document_history_on_the_request_page(): void
    {
        $request = $this->saveDraftWithLetter('letter-original.pdf');

        $this->as($this->borrower)
            ->put(route('requests.update', $request), $this->requestPayload('submit', 'letter-corrected.pdf'))
            ->assertSessionHasNoErrors();

        $this->assertSame(RequestStatus::UnderSpmu, $request->fresh()->status);

        // The Action Officer handling verification does not get the investigation panel.
        $this->as($this->officer)
            ->get(route('requests.show', $request))
            ->assertOk()
            ->assertDontSee('data-document-history', false);

        $this->as($this->officer)
            ->post(route('verifications.verify', $request), [
                'decision' => 'VERIFIED',
                'details_complete' => '1',
                'documents_complete' => '1',
                'availability_verified' => '1',
                'confirm_e_signature' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->as($this->head)
            ->get(route('requests.show', $request))
            ->assertOk()
            ->assertSee('data-document-history', false)
            ->assertSee('Document History')
            ->assertSee('letter-original.pdf')
            ->assertSee('letter-corrected.pdf')
            ->assertSee('Superseded')
            ->assertSee('not valid for current use')
            ->assertSee($this->borrower->full_name);

        // The borrower's own page and ICTU never get the panel.
        $this->as($this->borrower)
            ->get(route('requests.show', $request))
            ->assertOk()
            ->assertDontSee('data-document-history', false);
        $this->as($this->ictu)->get(route('requests.show', $request))->assertForbidden();
    }

    public function test_return_for_revision_and_resubmission_keep_working_without_any_replacement_reason_field(): void
    {
        $request = $this->saveDraftWithLetter('letter-original.pdf');

        // The borrower form collects no replacement reason.
        $this->as($this->borrower)
            ->get(route('requests.edit', $request))
            ->assertOk()
            ->assertDontSee('document_replacement_reason', false)
            ->assertDontSee('Reason for replacing');

        // Save Draft, then E-sign & Submit.
        $this->as($this->borrower)
            ->put(route('requests.update', $request), $this->requestPayload('submit'))
            ->assertSessionHasNoErrors();
        $this->assertSame(RequestStatus::UnderSpmu, $request->fresh()->status);

        // Return for Revision still requires its revision instructions.
        $this->as($this->officer)
            ->post(route('verifications.verify', $request), ['decision' => 'RETURNED_FOR_REVISION'])
            ->assertSessionHasErrors();
        $this->assertSame(RequestStatus::UnderSpmu, $request->fresh()->status);

        $this->as($this->officer)
            ->post(route('verifications.verify', $request), [
                'decision' => 'RETURNED_FOR_REVISION',
                'remarks' => 'Upload a clearer Borrowing Request Letter.',
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame(RequestStatus::ReturnedForRevision, $request->fresh()->status);

        // Resubmission with a corrected letter creates request version 2.
        $this->as($this->borrower)
            ->put(route('requests.update', $request), $this->requestPayload('submit', 'letter-revised.pdf'))
            ->assertSessionHasNoErrors();

        $resubmitted = $request->fresh();
        $this->assertSame(RequestStatus::UnderSpmu, $resubmitted->status);
        $this->assertSame(2, $resubmitted->current_version_no);

        // Both files are retained, and each upload was audited with the real actor and no reason.
        $this->assertSame(
            ['letter-original.pdf', 'letter-revised.pdf'],
            RequestSupportingDocument::query()->where('request_id', $request->id)->with('file')->orderBy('id')->get()->pluck('file.original_name')->all()
        );

        $events = AuditEvent::query()
            ->whereIn('action_code', ['REQUEST_SUPPORTING_DOCUMENT_UPLOADED', 'REQUEST_SUPPORTING_DOCUMENT_REPLACED'])
            ->where('record_id', $request->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $events);
        foreach ($events as $event) {
            $this->assertSame($this->borrower->id, $event->actor_user_id);
            $this->assertNull($event->reason);
        }
        $this->assertSame([1, 2], $events->pluck('after_json.request_version_no')->all());
    }

    public function test_a_rejected_upload_writes_no_document_audit_event(): void
    {
        $request = $this->saveDraftWithLetter('letter-original.pdf');

        $payload = $this->requestPayload('draft');
        $payload['approved_request_letter'] = UploadedFile::fake()->create('notes.txt', 4, 'text/plain');

        $this->as($this->borrower)
            ->put(route('requests.update', $request), $payload)
            ->assertSessionHasErrors('approved_request_letter');

        $this->assertSame(1, RequestSupportingDocument::query()->where('request_id', $request->id)->count());
        $this->assertSame(0, AuditEvent::query()->where('action_code', 'REQUEST_SUPPORTING_DOCUMENT_REPLACED')->count());
        $this->assertSame(1, AuditEvent::query()->where('action_code', 'REQUEST_SUPPORTING_DOCUMENT_UPLOADED')->count());
    }

    public function test_audit_trail_pages_and_filters_across_more_than_500_events_and_keeps_audiences_separate(): void
    {
        $item = $this->createInventoryItem('Paginated Investigation Item', 5);
        $operationalBefore = $this->as($this->head)->get(route('reports.audit'))->viewData('events')->total();

        // 520 operational events, the oldest 400+ days back, plus a handful of technical ones.
        $rows = [];
        for ($i = 1; $i <= 520; $i++) {
            $rows[] = $this->auditRow('INVENTORY_ITEM_UPDATED', InventoryItem::class, $item->id, $this->head->id, now()->subDays(400)->addMinutes($i), $i === 1 ? 'needle-oldest-operational' : "operational filler {$i}");
        }
        for ($i = 1; $i <= 6; $i++) {
            $rows[] = $this->auditRow('USER_ACCOUNT_UPDATED', User::class, $this->borrower->id, $this->ictu->id, now()->subDays(400)->addMinutes($i), $i === 1 ? 'needle-oldest-technical' : "technical filler {$i}");
        }
        // Pairs the category rules do not recognise follow the actor, as before.
        $rows[] = $this->auditRow('CUSTOM_WIDGET_TOUCHED', 'Widget', 1, $this->ictu->id, now()->subDays(2), 'needle-unknown-by-ictu');
        $rows[] = $this->auditRow('CUSTOM_WIDGET_TOUCHED', 'Widget', 2, $this->head->id, now()->subDays(2), 'needle-unknown-by-head');
        $rows[] = $this->auditRow('CUSTOM_WIDGET_TOUCHED', 'Widget', 3, null, now()->subDays(2), 'needle-unknown-by-system');
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('audit_events')->insert($chunk);
        }

        // Head: every operational and account event is counted and reachable; default page size is seven.
        $headPage = $this->as($this->head)->get(route('reports.audit'))->assertOk();
        $events = $headPage->viewData('events');
        $this->assertSame($operationalBefore + 528, $events->total());
        $this->assertSame(7, $events->perPage());
        $this->assertCount(7, $events->items());
        $headPage->assertDontSee('needle-oldest-operational');

        $lastPage = $this->as($this->head)->get(route('reports.audit', ['page' => $events->lastPage()]))->assertOk();
        $lastPage->assertSee('needle-oldest-operational');

        // Search, category, actor, date and page-size filters query the database.
        $this->as($this->head)->get(route('reports.audit', ['q' => 'needle-oldest-operational']))
            ->assertOk()
            ->assertSee('needle-oldest-operational')
            ->assertSee('1 matching record');
        $this->assertSame(519, $this->as($this->head)->get(route('reports.audit', ['q' => 'operational filler', 'category' => 'INVENTORY']))->viewData('events')->total());
        $this->assertSame(1, $this->as($this->head)->get(route('reports.audit', ['q' => 'Paginated Investigation Item']))->viewData('events')->total());
        $this->assertSame(14, $this->as($this->head)->get(route('reports.audit', ['per_page' => 14]))->viewData('events')->count());
        $this->assertSame(0, $this->as($this->head)->get(route('reports.audit', ['q' => 'needle-oldest-operational', 'date' => '30_DAYS']))->viewData('events')->total());
        $this->assertSame(1, $this->as($this->head)->get(route('reports.audit', ['actor' => 'SYSTEM', 'q' => 'needle-unknown']))->viewData('events')->total());
        $this->assertSame(
            521,
            $this->as($this->head)->get(route('reports.audit', ['record_type' => 'InventoryItem', 'record_id' => $item->id]))->viewData('events')->total()
        );

        // Paging keeps the active filters.
        $this->as($this->head)->get(route('reports.audit', ['category' => 'INVENTORY', 'per_page' => 14, 'page' => 2]))
            ->assertOk()
            ->assertSee('15–28 of', false);

        // The retired ICTU classification has no Audit Trail.
        $this->as($this->ictu)->get(route('reports.audit'))->assertForbidden();

        // Account events reach the Head, who now administers accounts.
        $this->assertSame(1, $this->as($this->head)->get(route('reports.audit', ['category' => 'ACCOUNT', 'q' => 'needle-oldest-technical']))->viewData('events')->total());
        $this->assertSame(6, $this->as($this->head)->get(route('reports.audit', ['record_type' => 'User', 'record_id' => $this->borrower->id]))->viewData('events')->total());

        // Other technical records, such as an unclassified event by a legacy ICTU actor, stay out.
        $this->assertSame(0, $this->as($this->head)->get(route('reports.audit', ['q' => 'needle-unknown-by-ictu']))->viewData('events')->total());
        $this->as($this->head)->get(route('reports.audit', ['q' => 'needle-unknown-by-head']))->assertSee('needle-unknown-by-head');

        // Borrowers and Action Officers have no Audit Trail at all.
        $this->as($this->borrower)->get(route('reports.audit'))->assertForbidden();
        $this->as($this->officer)->get(route('reports.audit'))->assertForbidden();
        $this->as($this->officer)->get(route('reports.audit', ['record_type' => 'InventoryItem', 'record_id' => $item->id]))->assertForbidden();
    }

    public function test_older_events_without_an_actor_snapshot_show_no_invented_role(): void
    {
        DB::table('audit_events')->insert(
            $this->auditRow('INVENTORY_ITEM_UPDATED', InventoryItem::class, $this->borrowableItem->id, $this->head->id, now()->subYear(), 'legacy-event-without-snapshot')
        );

        $this->as($this->head)
            ->get(route('reports.audit', ['q' => 'legacy-event-without-snapshot']))
            ->assertOk()
            ->assertSee('legacy-event-without-snapshot')
            ->assertSee($this->head->full_name)
            ->assertDontSee('at the time');

        // Nothing rewrote the historical row.
        $this->assertNull(AuditEvent::query()->where('reason', 'legacy-event-without-snapshot')->sole()->actor_name);
    }

    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        return $this->withSession(['active_workspace' => $user->primaryWorkspace()])->actingAs($user);
    }

    private function classificationUser(AccessClassification $classification): User
    {
        return User::query()
            ->where('access_classification', $classification->value)
            ->firstOrFail();
    }

    private function inventoryPayload(string $name): array
    {
        return [
            'category_id' => DB::table('inventory_categories')->value('id'),
            'unit_id' => DB::table('units_of_measure')->value('id'),
            'unique_description' => $name,
            'borrowable' => 1,
            'active' => 1,
        ];
    }

    private function createInventoryItem(string $name, int $quantity): InventoryItem
    {
        $this->as($this->head)
            ->post(route('inventory.store'), $this->inventoryPayload($name) + [
                'total_quantity' => $quantity,
                'condition_code' => 'SERVICEABLE',
            ])
            ->assertSessionHasNoErrors();

        return InventoryItem::query()->where('unique_description', $name)->firstOrFail();
    }

    private function saveDraftWithLetter(string $filename): BorrowingRequest
    {
        $this->as($this->borrower)
            ->post(route('requests.store'), $this->requestPayload('draft', $filename))
            ->assertSessionHasNoErrors();

        return BorrowingRequest::query()->with('currentVersion')->latest('id')->firstOrFail();
    }

    private function requestPayload(string $intent, ?string $letterFilename = null): array
    {
        $pickupDay = app(OperationalCalendarService::class)
            ->nextOpenDate(OperationalCalendarService::PICKUP, now()->addDays(2), true);
        $scheduleDate = $pickupDay->copy()->addDay();

        $payload = [
            'purpose_event' => 'Audit investigation activity',
            'event_details' => 'Audit investigation activity',
            'location' => 'Municipal activity venue',
            'requesting_organizational_unit_id' => $this->borrower->organizational_unit_id,
            'schedule_date' => $scheduleDate->toDateString(),
            'return_date' => $scheduleDate->copy()->addDay()->toDateString(),
            'represents_student_activity' => '0',
            'off_campus' => '1',
            'intent' => $intent,
            'borrower_acknowledgement' => '1',
            'confirm_e_signature' => '1',
            'item_ids' => [$this->borrowableItem->id],
            'quantities' => [$this->borrowableItem->id => 1],
            'locations' => [$this->borrowableItem->id => 'OFF_CAMPUS'],
        ];

        if ($letterFilename !== null) {
            $payload['approved_request_letter'] = UploadedFile::fake()->createWithContent($letterFilename, \Tests\Support\EvidenceFixtures::pdfBytes($letterFilename));
        }

        return $payload;
    }

    private function auditRow(string $action, string $recordType, int $recordId, ?int $actorId, \DateTimeInterface $occurredAt, string $reason): array
    {
        return [
            'actor_user_id' => $actorId,
            'action_code' => $action,
            'record_type' => $recordType,
            'record_id' => $recordId,
            'occurred_at' => $occurredAt,
            'reason' => $reason,
            'before_json' => null,
            'after_json' => null,
            'correlation_id' => (string) Str::uuid(),
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ];
    }

    private function registerSignature(User $user): void
    {
        $bytes = \Tests\Support\EvidenceFixtures::signatureBytes('investigation-signature-'.$user->id);
        $path = 'tests/signatures/'.$user->id.'/signature.png';
        Storage::disk('local')->put($path, $bytes);

        $file = StoredFile::query()->create([
            'uploaded_by_user_id' => $user->id,
            'disk' => 'local',
            'storage_path' => $path,
            'original_name' => 'signature.png',
            'mime_type' => 'image/png',
            'byte_size' => strlen($bytes),
            'sha256' => hash('sha256', $bytes),
            'classification' => 'SIGNATURE',
        ]);

        UserSignature::query()->create([
            'user_id' => $user->id,
            'stored_file_id' => $file->id,
            'effective_from' => now()->subMinute(),
            'status' => 'ACTIVE',
        ]);
    }
}
