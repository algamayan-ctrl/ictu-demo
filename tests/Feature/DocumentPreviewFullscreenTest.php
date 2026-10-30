<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Enums\RequestStatus;
use App\Models\BorrowingRequest;
use App\Models\RequestSupportingDocument;
use App\Models\StoredFile;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Borrower document preview: opt-in Full screen control on the shared
 * document viewer. Staff preview pages stay unchanged.
 */
class DocumentPreviewFullscreenTest extends TestCase
{
    use RefreshDatabase;

    // The button's own markup (the script also mentions the attribute name).
    private const TOGGLE = 'data-document-fullscreen-label>Full screen</span>';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
    }

    private function user(AccessClassification $classification): User
    {
        return User::query()
            ->where('access_classification', $classification->value)
            ->firstOrFail();
    }

    private function storedFile(User $uploader, string $name, string $mime, string $bytes): StoredFile
    {
        $path = 'tests/fullscreen/'.$name;
        Storage::disk('local')->put($path, $bytes);

        return StoredFile::query()->create([
            'uploaded_by_user_id' => $uploader->id,
            'disk' => 'local',
            'storage_path' => $path,
            'original_name' => $name,
            'mime_type' => $mime,
            'byte_size' => strlen($bytes),
            'sha256' => hash('sha256', $bytes),
            'classification' => 'REQUEST_SUPPORTING_DOCUMENT',
        ]);
    }

    /** A current request letter, so both the borrower and SPMU may view it. */
    private function requestLetter(User $borrower): StoredFile
    {
        $request = BorrowingRequest::query()->create([
            'request_no' => 'BR-FULLSCREEN-001',
            'borrower_user_id' => $borrower->id,
            'accountable_unit_id' => $borrower->organizational_unit_id,
            'current_version_no' => 1,
            'status' => RequestStatus::UnderSpmu,
        ]);

        $version = $request->versions()->create([
            'version_no' => 1,
            'purpose_event' => 'Full screen preview test',
            'location' => 'CSPC Campus',
            'needed_from' => now()->addDay(),
            'return_due_at' => now()->addDays(2),
            'created_by_user_id' => $borrower->id,
        ]);

        $file = $this->storedFile($borrower, 'request-letter.pdf', 'application/pdf', '%PDF-1.4 fullscreen-test');

        RequestSupportingDocument::query()->create([
            'request_id' => $request->id,
            'request_version_id' => $version->id,
            'document_type' => RequestSupportingDocument::TYPE_REQUEST_LETTER,
            'version_no' => 1,
            'stored_file_id' => $file->id,
            'uploaded_by_user_id' => $borrower->id,
            'uploaded_at' => now(),
            'verification_status' => RequestSupportingDocument::STATUS_PENDING,
            'is_current' => true,
        ]);

        return $file;
    }

    public function test_borrower_pdf_preview_has_a_full_screen_control(): void
    {
        $borrower = $this->user(AccessClassification::BorrowerOnly);
        $file = $this->requestLetter($borrower);

        $this->withSession(['active_workspace' => 'BORROWER'])
            ->actingAs($borrower)
            ->get(route('files.preview', $file))
            ->assertOk()
            ->assertSee(self::TOGGLE, false)
            ->assertSee('aria-pressed="false"', false)
            ->assertSeeText('Full screen')
            // The existing embedded viewer is unchanged.
            ->assertSee(route('files.show', $file, false), false);
    }

    public function test_borrower_image_preview_has_a_full_screen_control(): void
    {
        $borrower = $this->user(AccessClassification::BorrowerOnly);
        $file = $this->storedFile($borrower, 'scan.png', 'image/png', 'fake-png-bytes');

        $this->withSession(['active_workspace' => 'BORROWER'])
            ->actingAs($borrower)
            ->get(route('files.preview', $file))
            ->assertOk()
            ->assertSee(self::TOGGLE, false)
            ->assertSee('data-scanned-image-viewer', false);
    }

    public function test_no_full_screen_control_when_the_file_cannot_be_previewed(): void
    {
        $borrower = $this->user(AccessClassification::BorrowerOnly);
        $file = $this->storedFile($borrower, 'notes.txt', 'text/plain', 'plain text');

        $this->withSession(['active_workspace' => 'BORROWER'])
            ->actingAs($borrower)
            ->get(route('files.preview', $file))
            ->assertOk()
            ->assertSee('Preview is unavailable for this file type.')
            ->assertDontSee(self::TOGGLE, false);
    }

    public function test_staff_preview_of_the_same_document_is_unchanged(): void
    {
        $borrower = $this->user(AccessClassification::BorrowerOnly);
        $file = $this->requestLetter($borrower);

        $this->withSession(['active_workspace' => 'SPMU'])
            ->actingAs($this->user(AccessClassification::SpmuHead))
            ->get(route('files.preview', $file))
            ->assertOk()
            ->assertSee(route('files.show', $file, false), false)
            ->assertDontSee(self::TOGGLE, false)
            // No full-screen styles or script are added to staff pages at all.
            ->assertDontSee('data-document-fullscreen-toggle', false);
    }

    public function test_full_screen_does_not_change_file_access(): void
    {
        $owner = $this->user(AccessClassification::BorrowerOnly);
        $file = $this->requestLetter($owner);

        $otherBorrower = User::factory()->create([
            'access_classification' => AccessClassification::BorrowerOnly,
        ]);

        $this->withSession(['active_workspace' => 'BORROWER'])
            ->actingAs($otherBorrower)
            ->get(route('files.preview', $file))
            ->assertForbidden();
    }
}
