<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Enums\ApprovalStage;
use App\Enums\RequestStatus;
use App\Models\ApprovalStep;
use App\Models\BorrowingRequest;
use App\Models\RequestVersion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditRequestRevisionRemarksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_edit_request_shows_action_officer_return_remarks_and_timestamp(): void
    {
        $borrower = $this->user(AccessClassification::BorrowerOnly);
        $officer = $this->user(AccessClassification::SpmuOfficer);
        $version = $this->returnedRequestVersion($borrower, 1);
        $returnedAt = CarbonImmutable::parse('2026-09-27 14:30:00', 'Asia/Manila');

        $this->returnForRevision(
            $version,
            $officer,
            1,
            'Please replace the unreadable approval letter.',
            $returnedAt
        );

        $this->editResponse($borrower, $version->request)
            ->assertSeeText('Action required')
            ->assertSeeText('Returned for Revision')
            ->assertSeeText('Please update the request based on the review remarks below.')
            ->assertSeeText('Review remarks')
            ->assertSeeText('Please replace the unreadable approval letter.')
            ->assertSeeText('Returned by: SPMU Action Officer')
            ->assertSeeText('27 Sep 2026, 2:30 PM');
    }

    public function test_edit_request_shows_only_the_current_head_admin_return_remarks(): void
    {
        $borrower = $this->user(AccessClassification::BorrowerOnly);
        $officer = $this->user(AccessClassification::SpmuOfficer);
        $head = $this->user(AccessClassification::SpmuHead);
        $oldVersion = $this->returnedRequestVersion($borrower, 1);

        $this->returnForRevision(
            $oldVersion,
            $officer,
            1,
            'Older Action Officer correction that remains in request history.',
            CarbonImmutable::parse('2026-09-24 09:00:00', 'Asia/Manila')
        );

        $oldVersion->request->update(['current_version_no' => 2]);
        $currentVersion = $this->version($oldVersion->request, $borrower, 2);

        ApprovalStep::query()->create([
            'request_version_id' => $currentVersion->id,
            'approver_user_id' => $officer->id,
            'stage_code' => ApprovalStage::Spmu,
            'sequence_no' => 1,
            'decision' => 'VERIFIED',
            'decided_at' => CarbonImmutable::parse('2026-09-26 09:00:00', 'Asia/Manila'),
            'remarks' => 'An earlier verification note that is not a return instruction.',
        ]);

        $this->returnForRevision(
            $currentVersion,
            $head,
            2,
            'Please correct the expected return date before resubmitting.',
            CarbonImmutable::parse('2026-09-27 16:45:00', 'Asia/Manila')
        );

        $this->assertDatabaseHas('approval_steps', [
            'request_version_id' => $oldVersion->id,
            'remarks' => 'Older Action Officer correction that remains in request history.',
        ]);

        $this->editResponse($borrower, $oldVersion->request)
            ->assertSeeText('Please correct the expected return date before resubmitting.')
            ->assertSeeText('Returned by: SPMU Head/Admin')
            ->assertSeeText('27 Sep 2026, 4:45 PM')
            ->assertDontSeeText('Older Action Officer correction that remains in request history.')
            ->assertDontSeeText('An earlier verification note that is not a return instruction.');
    }

    public function test_edit_request_uses_the_legacy_fallback_when_return_remarks_are_missing(): void
    {
        $borrower = $this->user(AccessClassification::BorrowerOnly);
        $officer = $this->user(AccessClassification::SpmuOfficer);
        $version = $this->returnedRequestVersion($borrower, 1);

        $this->returnForRevision(
            $version,
            $officer,
            1,
            null,
            CarbonImmutable::parse('2026-09-27 10:15:00', 'Asia/Manila')
        );

        $this->editResponse($borrower, $version->request)
            ->assertSeeText('No review remarks were recorded. Please contact SPMU for clarification.')
            ->assertSeeText('Returned by: SPMU Action Officer')
            ->assertSeeText('27 Sep 2026, 10:15 AM');
    }

    private function user(AccessClassification $classification): User
    {
        return User::query()
            ->where('access_classification', $classification->value)
            ->firstOrFail();
    }

    private function returnedRequestVersion(User $borrower, int $versionNo): RequestVersion
    {
        $request = BorrowingRequest::query()->create([
            'request_no' => 'REVISION-'.uniqid(),
            'borrower_user_id' => $borrower->id,
            'accountable_unit_id' => $borrower->organizational_unit_id,
            'current_version_no' => $versionNo,
            'status' => RequestStatus::ReturnedForRevision,
        ]);

        return $this->version($request, $borrower, $versionNo);
    }

    private function version(
        BorrowingRequest $request,
        User $borrower,
        int $versionNo
    ): RequestVersion {
        $scheduleDate = CarbonImmutable::parse('2026-10-05', 'Asia/Manila');

        return $request->versions()->create([
            'version_no' => $versionNo,
            'created_by_user_id' => $borrower->id,
            'purpose_event' => 'Revision remarks coverage',
            'location' => 'CSPC Campus',
            'schedule_date' => $scheduleDate,
            'return_date' => $scheduleDate->addDay(),
            'needed_from' => $scheduleDate->startOfDay(),
            'return_due_at' => $scheduleDate->addDay()->endOfDay(),
            'off_campus' => false,
            'represents_student_activity' => false,
        ]);
    }

    private function returnForRevision(
        RequestVersion $version,
        User $reviewer,
        int $sequenceNo,
        ?string $remarks,
        CarbonImmutable $returnedAt
    ): ApprovalStep {
        return ApprovalStep::query()->create([
            'request_version_id' => $version->id,
            'approver_user_id' => $reviewer->id,
            'stage_code' => ApprovalStage::Spmu,
            'sequence_no' => $sequenceNo,
            'decision' => 'RETURNED_FOR_REVISION',
            'decided_at' => $returnedAt,
            'remarks' => $remarks,
        ]);
    }

    private function editResponse(User $borrower, BorrowingRequest $request): \Illuminate\Testing\TestResponse
    {
        return $this->withSession(['active_workspace' => 'BORROWER'])
            ->actingAs($borrower)
            ->get(route('requests.edit', $request))
            ->assertOk();
    }
}
