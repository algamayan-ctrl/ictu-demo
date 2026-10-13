<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Models\BillingStatement;
use App\Models\Payment;
use App\Models\StoredFile;
use App\Models\User;
use App\Services\ProtectedFileService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Who may open a scanned Cashier paid receipt, and who may load another
 * user's profile picture.
 *
 * Both are decided from the account's current access classification. A value
 * left in the session by an earlier workspace grants nothing.
 */
class ProtectedFileAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private const RECEIPT_BYTES = '%PDF-1.4 cashier-paid-receipt';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        Storage::fake('local');
        Storage::fake('s3');
    }

    /* ------------------------------------------------------------------ */
    /* Cashier paid receipts                                               */
    /* ------------------------------------------------------------------ */

    public function test_the_spmu_head_can_open_a_paid_receipt_uploaded_by_the_action_officer(): void
    {
        [$file] = $this->paidReceipt($this->borrower());

        $this->assertSame('CSPC_CASHIER_PAID_RECEIPT', $file->classification);
        $this->assertNotSame($this->spmuHead()->id, (int) $file->uploaded_by_user_id);

        $response = $this->actingAs($this->spmuHead())->get(route('files.show', $file));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame(self::RECEIPT_BYTES, $response->streamedContent());
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->actingAs($this->spmuHead())
            ->get(route('files.preview', $file))
            ->assertOk()
            ->assertViewIs('documents.file-preview');
    }

    public function test_the_uploading_officer_can_still_open_the_receipt(): void
    {
        [$file] = $this->paidReceipt($this->borrower());

        $response = $this->actingAs($this->officer())->get(route('files.show', $file));

        $response->assertOk();
        $this->assertSame(self::RECEIPT_BYTES, $response->streamedContent());
    }

    public function test_a_receipt_recorded_through_the_payment_form_opens_for_the_spmu_head(): void
    {
        $billing = $this->billing($this->borrower());

        $this->withSession(['active_workspace' => 'SPMU'])
            ->actingAs($this->officer())
            ->post(route('payments.store', $billing), [
                'evidence' => UploadedFile::fake()->createWithContent('receipt.pdf', self::RECEIPT_BYTES),
                'official_receipt_no' => 'OR-AUTHZ-0001',
                'receipt_date' => now()->toDateString(),
                'amount' => 100,
            ])
            ->assertSessionHasNoErrors();

        $payment = Payment::query()->where('billing_statement_id', $billing->id)->firstOrFail();
        $file = StoredFile::query()->findOrFail($payment->evidence_file_id);

        /* The classification the workflow really writes is the one the rule must recognize. */
        $this->assertSame('CSPC_CASHIER_PAID_RECEIPT', $file->classification);

        $this->actingAs($this->spmuHead())->get(route('files.show', $file))->assertOk();
        $this->actingAs($this->spmuHead())->get(route('files.preview', $file))->assertOk();
    }

    public function test_the_borrower_named_on_the_billing_can_open_their_own_receipt(): void
    {
        $borrower = $this->borrower();
        [$file] = $this->paidReceipt($borrower);

        $response = $this->actingAs($borrower)->get(route('files.show', $file));

        $response->assertOk();
        $this->assertSame(self::RECEIPT_BYTES, $response->streamedContent());
    }

    public function test_another_borrower_cannot_open_the_receipt(): void
    {
        [$file] = $this->paidReceipt($this->borrower());

        $outsider = $this->otherBorrower();

        $this->actingAs($outsider)->get(route('files.show', $file))->assertForbidden();
        $this->actingAs($outsider)->get(route('files.preview', $file))->assertForbidden();
    }

    public function test_a_borrower_account_with_a_stale_spmu_session_cannot_open_the_receipt(): void
    {
        [$file] = $this->paidReceipt($this->borrower());

        $outsider = $this->otherBorrower();

        $this->withSession(['active_workspace' => 'SPMU'])
            ->actingAs($outsider)
            ->get(route('files.show', $file))
            ->assertForbidden();

        $this->withSession(['active_workspace' => 'SPMU'])
            ->actingAs($outsider)
            ->get(route('files.preview', $file))
            ->assertForbidden();
    }

    public function test_the_ictu_maintainer_cannot_open_the_receipt(): void
    {
        [$file] = $this->paidReceipt($this->borrower());

        $this->actingAs($this->ictu())->get(route('files.show', $file))->assertForbidden();
        $this->actingAs($this->ictu())->get(route('files.preview', $file))->assertForbidden();
    }

    public function test_a_guest_is_never_served_the_receipt(): void
    {
        [$file] = $this->paidReceipt($this->borrower());

        $this->get(route('files.show', $file))->assertRedirect();
        $this->get(route('files.preview', $file))->assertRedirect();
    }

    public function test_recognizing_receipts_does_not_open_unclassified_protected_files_to_spmu(): void
    {
        /* Same uploader, same folder, but not a classification SPMU reviews. */
        $file = $this->storedFile($this->officer(), 'PROTECTED');

        $this->actingAs($this->spmuHead())->get(route('files.show', $file))->assertForbidden();
        $this->actingAs($this->spmuHead())->get(route('files.preview', $file))->assertForbidden();
    }

    public function test_a_receipt_stored_on_the_s3_disk_follows_the_same_rule(): void
    {
        config(['filesystems.protected_disk' => 's3']);

        [$file] = $this->paidReceipt($this->borrower());

        $this->assertSame('s3', $file->disk);

        $response = $this->actingAs($this->spmuHead())->get(route('files.show', $file));
        $response->assertOk();
        $this->assertSame(self::RECEIPT_BYTES, $response->streamedContent());

        $this->actingAs($this->otherBorrower())->get(route('files.show', $file))->assertForbidden();
    }

    /* ------------------------------------------------------------------ */
    /* Profile pictures                                                    */
    /* ------------------------------------------------------------------ */

    public function test_spmu_staff_can_load_another_users_picture_without_any_session_workspace(): void
    {
        $owner = $this->withPicture($this->borrower());

        $response = $this->actingAs($this->officer())->get(route('users.picture.show', $owner));

        $response->assertOk();
        $this->assertSame('picture-bytes', $response->streamedContent());
    }

    public function test_spmu_staff_keep_access_when_the_session_holds_another_workspace(): void
    {
        $owner = $this->withPicture($this->borrower());

        $this->withSession(['active_workspace' => 'BORROWER'])
            ->actingAs($this->spmuHead())
            ->get(route('users.picture.show', $owner))
            ->assertOk();
    }

    public function test_a_borrower_with_a_stale_spmu_session_cannot_load_another_users_picture(): void
    {
        $owner = $this->withPicture($this->borrower());

        $this->withSession(['active_workspace' => 'SPMU'])
            ->actingAs($this->otherBorrower())
            ->get(route('users.picture.show', $owner))
            ->assertForbidden();
    }

    public function test_an_account_moved_out_of_spmu_loses_picture_access_in_the_same_session(): void
    {
        $owner = $this->withPicture($this->borrower());
        $viewer = $this->spmuHead();

        $this->withSession(['active_workspace' => 'SPMU'])
            ->actingAs($viewer)
            ->get(route('users.picture.show', $owner))
            ->assertOk();

        /* Reclassified while signed in: the session still says SPMU. */
        $viewer->forceFill(['access_classification' => AccessClassification::BorrowerOnly])->save();

        $this->withSession(['active_workspace' => 'SPMU'])
            ->actingAs($viewer->fresh())
            ->get(route('users.picture.show', $owner))
            ->assertForbidden();
    }

    public function test_the_ictu_maintainer_cannot_load_another_users_picture_even_with_an_spmu_session(): void
    {
        $owner = $this->withPicture($this->borrower());

        $this->actingAs($this->ictu())->get(route('users.picture.show', $owner))->assertForbidden();

        $this->withSession(['active_workspace' => 'SPMU'])
            ->actingAs($this->ictu())
            ->get(route('users.picture.show', $owner))
            ->assertForbidden();
    }

    public function test_another_borrower_cannot_load_someone_elses_picture(): void
    {
        $owner = $this->withPicture($this->borrower());

        $this->actingAs($this->otherBorrower())
            ->get(route('users.picture.show', $owner))
            ->assertForbidden();
    }

    public function test_a_user_can_always_load_their_own_picture_by_user_route(): void
    {
        $owner = $this->withPicture($this->borrower());

        $response = $this->actingAs($owner)->get(route('users.picture.show', $owner));

        $response->assertOk();
        $this->assertSame('picture-bytes', $response->streamedContent());
    }

    public function test_a_guest_cannot_load_a_profile_picture(): void
    {
        $owner = $this->withPicture($this->borrower());

        $this->get(route('users.picture.show', $owner))->assertRedirect();
    }

    /* ------------------------------------------------------------------ */
    /* Fixture                                                             */
    /* ------------------------------------------------------------------ */

    private function seeded(AccessClassification $classification): User
    {
        return User::query()
            ->where('access_classification', $classification->value)
            ->firstOrFail();
    }

    private function borrower(): User
    {
        return $this->seeded(AccessClassification::BorrowerOnly);
    }

    private function officer(): User
    {
        return $this->seeded(AccessClassification::SpmuOfficer);
    }

    private function spmuHead(): User
    {
        return $this->seeded(AccessClassification::SpmuHead);
    }

    private function ictu(): User
    {
        return $this->seeded(AccessClassification::IctuMaintainer);
    }

    /** A second borrower account with no connection to the first one's records. */
    private function otherBorrower(): User
    {
        return User::factory()->create([
            'access_classification' => AccessClassification::BorrowerOnly,
            'organizational_unit_id' => $this->borrower()->organizational_unit_id,
        ]);
    }

    private function billing(User $borrower): BillingStatement
    {
        return BillingStatement::query()->create([
            'billing_no' => 'BILL-AUTHZ-'.fake()->unique()->numberBetween(1000, 999999),
            'borrower_user_id' => $borrower->id,
            'responsible_spmu_user_id' => $this->officer()->id,
            'issued_at' => now(),
            'total_amount' => 500,
            'status' => 'ISSUED',
        ]);
    }

    private function storedFile(User $uploader, string $classification): StoredFile
    {
        return app(ProtectedFileService::class)->storeBytes(
            self::RECEIPT_BYTES,
            'payment-evidence',
            'receipt.pdf',
            'application/pdf',
            'pdf',
            $classification,
            $uploader->id
        );
    }

    /**
     * A receipt as the Action Officer records it: uploaded by the officer,
     * attached to a payment on the borrower's billing statement.
     *
     * @return array{0: StoredFile, 1: Payment}
     */
    private function paidReceipt(User $borrower): array
    {
        $officer = $this->officer();
        $file = $this->storedFile($officer, 'CSPC_CASHIER_PAID_RECEIPT');

        $payment = Payment::query()->create([
            'billing_statement_id' => $this->billing($borrower)->id,
            'evidence_file_id' => $file->id,
            'recorded_by_user_id' => $officer->id,
            'verified_by_user_id' => $officer->id,
            'official_receipt_no' => 'OR-AUTHZ-'.fake()->unique()->numberBetween(1000, 999999),
            'receipt_date' => now()->toDateString(),
            'amount' => 100,
            'status' => 'VERIFIED',
            'verified_at' => now(),
        ]);

        return [$file, $payment];
    }

    private function withPicture(User $owner): User
    {
        $path = 'profile-pictures/'.$owner->id.'/private.png';

        Storage::disk('local')->put($path, 'picture-bytes');
        $owner->forceFill(['profile_picture_path' => $path])->save();

        return $owner;
    }
}
