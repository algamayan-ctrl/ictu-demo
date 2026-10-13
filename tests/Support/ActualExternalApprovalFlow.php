<?php

namespace Tests\Support;

use App\Enums\AccessClassification;
use App\Models\{BorrowerRegistration, BorrowingRequest, InventoryItem, User};
use App\Services\UserRoleAssignmentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;

/** Endpoint-driven fixtures; never manufacture an approved request/custody/packet. */
trait ActualExternalApprovalFlow
{
    private function sao(): User
    {
        return User::where('email', 'spmu@spmu.test')->sole();
    }

    private function headUser(): User
    {
        return User::where('email', 'spmu-head@spmu.test')->sole();
    }

    private function as(User $user): static
    {
        Cache::flush();
        return $this->actingAs($user)->withSession(['active_workspace' => $user->primaryWorkspace()]);
    }

    private function sign(User $user, string $ink): void
    {
        $this->as($user)->post(route('profile.signature'), [
            'signature' => UploadedFile::fake()->createWithContent('signature.png', EvidenceFixtures::signatureBytes($ink)),
        ])->assertSessionHasNoErrors();
    }

    private function payload(array $items, bool $activity, string $venue): array
    {
        $date = now()->addWeekdays(3);
        return [
            'purpose_event' => 'Synthetic corrected external approval', 'location' => $venue,
            'schedule_date' => $date->toDateString(), 'return_date' => $date->copy()->addWeekdays(2)->toDateString(),
            'item_ids' => array_keys($items), 'quantities' => array_map(fn ($i) => $i[0], $items),
            'locations' => array_map(fn ($i) => $i[1], $items),
            'represents_student_activity' => $activity ? 1 : 0,
            'student_organization' => $activity ? 'Synthetic represented activity' : null,
            'intent' => 'submit', 'borrower_acknowledgement' => 1, 'confirm_e_signature' => 1,
        ];
    }

    private function uploads(bool $activity, string $tag): array
    {
        $result = ['approved_request_letter' => UploadedFile::fake()->createWithContent($tag.'-letter.pdf', EvidenceFixtures::pdfBytes('Synthetic '.$tag.' letter'))];
        if ($activity) {
            $result['permission_to_conduct_letter'] = UploadedFile::fake()->createWithContent($tag.'-ptc.pdf', EvidenceFixtures::pdfBytes('Synthetic '.$tag.' PTC'));
        }
        return $result;
    }

    private function ready(array $items = [], bool $activity = false): BorrowingRequest
    {
        if (! $items) {
            $items = [InventoryItem::where('unique_description', 'Barricade')->sole()->id => [2, 'OFF_CAMPUS']];
        }
        $borrower = User::factory()->create(['employee_no' => null, 'employment_type' => null, 'organizational_unit_id' => null, 'access_classification' => AccessClassification::BorrowerOnly]);
        BorrowerRegistration::create(['user_id' => $borrower->id, 'registration_type' => 'EXTERNAL', 'company_name' => 'Reviewed External Agency', 'status' => 'APPROVED']);
        app(UserRoleAssignmentService::class)->synchronize($borrower, AccessClassification::BorrowerOnly, $this->sao()->id);
        $this->sign($borrower, 'Original borrower ink');
        $this->post(route('requests.store'), $this->payload($items, $activity, 'Original external venue') + $this->uploads($activity, 'original'))->assertSessionHasNoErrors();
        $request = BorrowingRequest::where('borrower_user_id', $borrower->id)->sole();
        $offCampus = in_array('OFF_CAMPUS', array_column($items, 1), true);
        $this->as($offCampus ? $this->sao() : $this->headUser())->post(
            route($offCampus ? 'verifications.verify' : 'approvals.decide', $request),
            ['decision' => 'RETURNED_FOR_REVISION', 'remarks' => 'Correct the venue and evidence.']
        )->assertSessionHasNoErrors();
        $borrower->borrowerRegistration->update(['company_name' => 'Later profile agency']);
        $this->sign($borrower, 'Corrected borrower ink');
        $this->put(route('requests.update', $request), $this->payload($items, $activity, 'Corrected external venue') + $this->uploads($activity, 'corrected'))->assertSessionHasNoErrors();
        $this->assertSame(2, $request->fresh()->current_version_no);
        $this->assertNull($request->fresh()->custody);
        $this->assertSame(0, $request->fresh()->currentVersion->items()->whereHas('allocation')->count());
        if ($offCampus) {
            $this->sign($this->sao(), 'Owned SAO ink');
            $this->as($this->sao())->post(route('verifications.verify', $request), [
                'decision' => 'VERIFIED', 'reviewed_version_id' => $request->fresh()->currentVersion->id,
                'details_complete' => 1, 'documents_complete' => 1, 'availability_verified' => 1, 'confirm_e_signature' => 1,
            ])->assertSessionHasNoErrors();
        }
        $this->sign($this->headUser(), 'Owned Head ink');
        return $request->fresh();
    }

    private function approve(BorrowingRequest $request, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->as($this->headUser())->post(route('approvals.decide', $request), array_replace([
            'decision' => 'APPROVED', 'reviewed_version_id' => $request->currentVersion->id,
            'confirm_e_signature' => 1, 'details_complete' => 1, 'documents_complete' => 1, 'availability_verified' => 1,
        ], $overrides));
    }
}
