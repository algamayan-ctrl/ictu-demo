<?php

namespace Tests\Support;

use App\Enums\AccessClassification;
use App\Enums\RequestStatus;
use App\Models\Allocation;
use App\Models\ApprovalStep;
use App\Models\BorrowerRegistration;
use App\Models\BorrowingRequest;
use App\Models\CustodyLine;
use App\Models\CustodyTransaction;
use App\Models\GatePass;
use App\Models\InventoryItem;
use App\Models\RequestItem;
use App\Models\SignatureSnapshot;
use App\Models\User;
use App\Models\UserSignature;
use App\Services\ProtectedFileService;
use App\Services\SignatureService;
use App\Services\UserRoleAssignmentService;
use Tests\TestCase;

/** Synthetic already-approved rendering data, not an actual workflow approval. */
class ApprovedConditionalDocumentFixture
{
    public static function create(TestCase $test, string $form, bool $external = true, ?string $company = 'Reviewed Agency'): CustodyTransaction
    {
        $head = User::where('access_classification', AccessClassification::SpmuHead)->firstOrFail();
        $borrower = $external
            ? User::factory()->create(['employee_no' => null, 'organizational_unit_id' => null,
                'employment_type' => null, 'employment_status' => null, 'designation' => null])
            : User::where('access_classification', AccessClassification::BorrowerOnly)->whereNotNull('organizational_unit_id')->firstOrFail();
        if ($external) {
            app(UserRoleAssignmentService::class)->synchronize($borrower, AccessClassification::BorrowerOnly);
            BorrowerRegistration::create(['user_id' => $borrower->id, 'registration_type' => 'EXTERNAL',
                'company_name' => 'Current Profile Agency', 'status' => 'APPROVED']);
        }
        $request = BorrowingRequest::create(['request_no' => 'SYNTH-C02-'.uniqid(), 'borrower_user_id' => $borrower->id,
            'accountable_unit_id' => $external ? null : $borrower->organizational_unit_id, 'current_version_no' => 1,
            'status' => RequestStatus::ApprovedReadyForRelease, 'final_approved_at' => now()]);
        $version = $request->versions()->create(['version_no' => 1, 'purpose_event' => 'Synthetic approved conditional rendering',
            'location' => 'Synthetic venue', 'schedule_date' => now()->addDay()->toDateString(),
            'return_date' => now()->addDays(2)->toDateString(), 'needed_from' => now()->addDay()->startOfDay(),
            'return_due_at' => now()->addDays(2)->endOfDay(), 'created_by_user_id' => $borrower->id,
            'affiliation_type' => $external ? 'EXTERNAL' : 'EMPLOYEE', 'external_company_name' => $external ? $company : null]);
        $signature = self::snapshot($test, $borrower, 'BORROWING_REQUEST', 'BORROWER');
        $version->update(['borrower_signature_snapshot_id' => $signature->id, 'signed_at' => now(), 'submitted_at' => now()]);
        $headSignature = self::snapshot($test, $head, 'SPMU_HEAD_APPROVAL', 'SPMU_HEAD');
        ApprovalStep::create(['request_version_id' => $version->id, 'stage_code' => 'SPMU', 'sequence_no' => 2,
            'decision' => 'APPROVED', 'approver_user_id' => $head->id, 'signature_snapshot_id' => $headSignature->id, 'decided_at' => now()]);
        $items = InventoryItem::with('unit')->where('active', true)->where('borrowable', true)
            ->where('laundry_required', $form === 'LAUNDRY_FORM');
        if ($form === 'GATE_PASS') {
            $items->where('off_campus_allowed', true);
        }
        $item = $items->firstOrFail();
        $requestItem = RequestItem::create(['request_version_id' => $version->id, 'inventory_item_id' => $item->id,
            'description_snapshot' => $item->unique_description, 'unit_snapshot' => $item->unit->unit_name,
            'requested_quantity' => 1, 'approved_quantity' => 1, 'use_location' => $form === 'GATE_PASS' ? 'OFF_CAMPUS' : 'ON_CAMPUS']);
        $allocation = Allocation::create(['request_item_id' => $requestItem->id, 'period_start' => $version->needed_from,
            'period_end' => $version->return_due_at, 'allocated_quantity' => 1, 'released_quantity' => 0,
            'restored_quantity' => 0, 'status' => 'ACTIVE', 'allocated_at' => now()]);
        $custody = CustodyTransaction::create(['custody_no' => 'SYNTH-CUS-C02-'.uniqid(), 'request_id' => $request->id,
            'request_version_id' => $version->id, 'borrower_user_id' => $borrower->id, 'status' => 'PREPARING_RELEASE',
            'scheduled_release_at' => now()->addDay()->setTime(9, 0), 'due_at' => $version->return_due_at]);
        $custodyLine = CustodyLine::create(['custody_transaction_id' => $custody->id, 'request_item_id' => $requestItem->id,
            'allocation_id' => $allocation->id, 'approved_quantity' => 1, 'quantity_to_receive' => 1,
            'actual_released_quantity' => 0, 'returned_quantity' => 0, 'item_status' => 'PENDING']);
        if ($form === 'GATE_PASS') {
            $sao = User::where('access_classification', AccessClassification::SpmuOfficer)->firstOrFail();
            $saoSignature = self::snapshot($test, $sao, 'SPMU_REQUEST_VERIFICATION', 'SPMU_ACTION_OFFICER');
            ApprovalStep::create(['request_version_id' => $version->id, 'stage_code' => 'SPMU', 'sequence_no' => 1,
                'decision' => 'VERIFIED', 'approver_user_id' => $sao->id, 'signature_snapshot_id' => $saoSignature->id, 'decided_at' => now()]);
            GatePass::create(['custody_transaction_id' => $custody->id, 'custody_line_id' => $custodyLine->id, 'bearer_name' => $borrower->full_name,
                'destination' => 'Synthetic venue', 'purpose' => $version->purpose_event, 'status' => 'READY_FOR_PRINTING',
                'prepared_verified_by_user_id' => $sao->id, 'prepared_verifier_signature_snapshot_id' => $saoSignature->id,
                'prepared_verified_at' => now(), 'approved_by_user_id' => $head->id,
                'approver_signature_snapshot_id' => $headSignature->id, 'approved_at' => now()]);
        }
        $test->actingAs($head);

        return $custody->fresh();
    }

    private static function snapshot(TestCase $test, User $user, string $purpose, string $role): SignatureSnapshot
    {
        $file = app(ProtectedFileService::class)->storeBytes(EvidenceFixtures::signatureBytes($role),
            'synthetic-c02-signatures', 'signature.png', 'image/png', 'png', 'SIGNATURE', $user->id);
        UserSignature::create(['user_id' => $user->id, 'stored_file_id' => $file->id,
            'effective_from' => now()->subMinute(), 'status' => 'ACTIVE']);
        $test->actingAs($user);

        return app(SignatureService::class)->snapshot($user, $purpose, $role);
    }
}
