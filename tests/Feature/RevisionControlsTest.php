<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Enums\ApprovalStage;
use App\Enums\RequestStatus;
use App\Models\ApprovalStep;
use App\Models\BorrowingRequest;
use App\Models\TemporaryDelegation;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\RequestWorkflowService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RevisionControlsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_final_access_classifications_enforce_borrowing_and_workspace_rules(): void
    {
        $this->assertClassification(AccessClassification::BorrowerOnly, true, ['BORROWER']);
        $this->assertClassification(AccessClassification::SpmuHead, false, ['SPMU']);
        $this->assertClassification(AccessClassification::SpmuOfficer, false, ['SPMU']);
        $this->assertClassification(AccessClassification::IctuMaintainer, false, ['ICTU']);
        $this->assertFalse(AccessClassification::RetiredInactive->isPortalEnabled());
        $this->assertSame([], AccessClassification::RetiredInactive->workspaces());

        $this->assertFalse(AccessClassification::GsuHead->isPortalEnabled());
        $this->assertFalse(AccessClassification::VpafHead->isPortalEnabled());
        $this->assertSame([], AccessClassification::GsuHead->workspaces());
        $this->assertSame([], AccessClassification::VpafHead->workspaces());
    }

    public function test_formal_spmu_temporary_delegate_uses_own_account_and_is_attributed_to_the_decision(): void
    {
        $spmuHead = User::where(
            'access_classification',
            AccessClassification::SpmuHead->value
        )->firstOrFail();

        $delegate = User::where(
            'access_classification',
            AccessClassification::SpmuOfficer->value
        )->firstOrFail();

        $borrower = User::where(
            'access_classification',
            AccessClassification::BorrowerOnly->value
        )->firstOrFail();

        $ictu = User::where(
            'access_classification',
            AccessClassification::IctuMaintainer->value
        )->firstOrFail();

        /*
         * A formal delegation is SPMU-only in the active workflow.
         * The acting officer still uses their own account.
         */
        $delegate->update([
            'organizational_unit_id' => $spmuHead->organizational_unit_id,
        ]);

        $delegation = TemporaryDelegation::create([
            'office_role' => 'SPMU',
            'absent_head_user_id' => $spmuHead->id,
            'delegate_user_id' => $delegate->id,
            'recorded_by_user_id' => $ictu->id,
            'authority_reference' => 'MEMO-TEST-001',
            'reason' => 'SPMU Head is unavailable.',
            'effective_from' => now()->subHour(),
            'effective_to' => now()->addDay(),
            'status' => 'ACTIVE',
        ]);

        $request = BorrowingRequest::create([
            'request_no' => 'BR-DELEGATE-001',
            'borrower_user_id' => $borrower->id,
            'accountable_unit_id' => $borrower->organizational_unit_id,
            'current_version_no' => 1,
            'status' => RequestStatus::UnderSpmu,
        ]);

        $version = $request->versions()->create([
            'version_no' => 1,
            'purpose_event' => 'SPMU delegation test',
            'location' => 'Campus',
            'needed_from' => now()->addDay(),
            'return_due_at' => now()->addDays(2),
            'created_by_user_id' => $borrower->id,
        ]);

        ApprovalStep::create([
            'request_version_id' => $version->id,
            'stage_code' => ApprovalStage::Spmu,
            'sequence_no' => 1,
            'received_at' => now(),
            'approver_user_id' => $delegate->id,
            'decision' => 'VERIFIED',
            'decided_at' => now(),
        ]);

        ApprovalStep::create([
            'request_version_id' => $version->id,
            'stage_code' => ApprovalStage::Spmu,
            'sequence_no' => 2,
            'received_at' => now(),
            'decision' => 'RECEIVED',
        ]);

        /*
         * Use REJECTED here so this focused test verifies delegation
         * attribution without depending on supporting-document and
         * inventory-reservation fixtures.
         */
        $this->withSession(['active_workspace' => 'SPMU'])
            ->actingAs($delegate)
            ->post(
                route('approvals.decide', $request),
                [
                    'decision' => 'REJECTED',
                    'remarks' => 'Delegated SPMU verification test.',
                ]
            )
            ->assertSessionHasNoErrors();

        $this->assertSame(
            RequestStatus::Rejected,
            $request->fresh()->status
        );

        $this->assertDatabaseHas('approval_steps', [
            'request_version_id' => $version->id,
            'stage_code' => ApprovalStage::Spmu->value,
            'sequence_no' => 2,
            'approver_user_id' => $delegate->id,
            'temporary_delegation_id' => $delegation->id,
            'decision' => 'REJECTED',
        ]);
    }

    public function test_retired_approved_letter_download_is_not_a_custody_creation_path(): void
    {
        $this->assertFalse(method_exists(DocumentService::class, 'requestLetter'));
        $this->assertFalse(method_exists(RequestWorkflowService::class, 'recordApprovedLetterDownload'));
        $this->assertFalse(app('router')->has('requests.recover-draft-document'));
    }

    /**
     * Early return uses the normal Return Inspection endpoint (custody.return),
     * classified automatically by calendar date - the legacy custody.early-
     * return route is intentionally retired and must not be recreated.
     */
    public function test_early_return_route_is_retired_under_the_current_policy(): void
    {
        $this->assertFalse(app('router')->has('custody.early-return'));
    }

    private function assertClassification(AccessClassification $classification, bool $mayBorrow, array $workspaces): void
    {
        $user = User::where('access_classification', $classification->value)->firstOrFail();
        $this->assertSame($mayBorrow, $user->mayBorrow());
        $this->assertSame($workspaces, $user->allowedWorkspaces());
    }


}
