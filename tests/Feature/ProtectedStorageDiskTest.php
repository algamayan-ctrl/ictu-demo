<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Enums\RequestStatus;
use App\Exceptions\ProtectedFileStorageException;
use App\Models\BorrowingRequest;
use App\Models\GeneratedDocument;
use App\Models\RequestSupportingDocument;
use App\Models\RequestVersion;
use App\Models\StoredFile;
use App\Models\User;
use App\Services\ProtectedFileService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Protected files on a configurable disk.
 *
 * New files go to the disk PROTECTED_FILES_DISK names ("local" unless an
 * environment says otherwise); every file is read back from the disk its own
 * record names. Both disks are Laravel storage fakes here: nothing in this
 * file reaches a real bucket.
 */
class ProtectedStorageDiskTest extends TestCase
{
    use RefreshDatabase;

    private ProtectedFileService $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        Storage::fake('local');
        Storage::fake('s3');

        $this->files = app(ProtectedFileService::class);
    }

    /* ------------------------------------------------------------------ */
    /* Writing                                                             */
    /* ------------------------------------------------------------------ */

    public function test_local_is_the_default_protected_disk(): void
    {
        $this->assertSame('local', config('filesystems.protected_disk'));
        $this->assertSame('local', $this->files->writeDisk());
        $this->assertSame('local', config('filesystems.default'), 'The default disk is not the protected disk and does not move with it.');
    }

    public function test_a_new_file_is_written_to_the_local_disk_by_default(): void
    {
        $bytes = '%PDF-1.4 local-default';

        $file = $this->files->storeBytes($bytes, 'generated-documents', 'slip.pdf', 'application/pdf', 'pdf', 'CONTROLLED_DOCUMENT');

        $this->assertSame('local', $file->disk);
        Storage::disk('local')->assertExists($file->storage_path);
        Storage::disk('s3')->assertMissing($file->storage_path);
        $this->assertSame($bytes, Storage::disk('local')->get($file->storage_path));
    }

    public function test_a_new_file_is_written_to_the_configured_s3_disk_and_records_that_disk(): void
    {
        config(['filesystems.protected_disk' => 's3']);

        $bytes = '%PDF-1.4 object-storage';

        $file = $this->files->storeBytes($bytes, 'generated-documents', 'slip.pdf', 'application/pdf', 'pdf', 'CONTROLLED_DOCUMENT');

        $this->assertSame('s3', $file->disk);
        $this->assertSame('s3', $file->fresh()->disk);
        Storage::disk('s3')->assertExists($file->storage_path);
        Storage::disk('local')->assertMissing($file->storage_path);
        $this->assertSame($bytes, Storage::disk('s3')->get($file->storage_path));
    }

    public function test_the_record_keeps_the_hash_size_classification_and_path_convention(): void
    {
        config(['filesystems.protected_disk' => 's3']);

        $bytes = random_bytes(2048);

        $file = $this->files->storeBytes($bytes, '/incident-evidence/', 'photo.png', 'image/png', '.png', 'INCIDENT_EVIDENCE', null);

        $this->assertSame(2048, (int) $file->byte_size);
        $this->assertSame(hash('sha256', $bytes), $file->sha256);
        $this->assertSame(hash('sha256', Storage::disk('s3')->get($file->storage_path)), $file->sha256);
        $this->assertSame('INCIDENT_EVIDENCE', $file->classification);
        $this->assertSame('photo.png', $file->original_name);
        $this->assertSame('image/png', $file->mime_type);
        /* folder/<uuid>.<extension>, exactly as before. */
        $this->assertMatchesRegularExpression(
            '#^incident-evidence/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.png$#',
            $file->storage_path
        );
    }

    public function test_an_upload_is_stored_with_its_original_name_type_hash_and_uploader(): void
    {
        config(['filesystems.protected_disk' => 's3']);

        $uploader = $this->borrower();
        $this->actingAs($uploader);

        $upload = UploadedFile::fake()->createWithContent('Signed Request Letter.pdf', '%PDF-1.4 signed-letter');

        $file = $this->files->storeUpload($upload, 'request-supporting-documents', 'REQUEST_SUPPORTING_DOCUMENT');

        $this->assertSame('s3', $file->disk);
        $this->assertSame('Signed Request Letter.pdf', $file->original_name);
        $this->assertSame('REQUEST_SUPPORTING_DOCUMENT', $file->classification);
        $this->assertSame($uploader->id, (int) $file->uploaded_by_user_id);
        $this->assertSame(strlen('%PDF-1.4 signed-letter'), (int) $file->byte_size);
        $this->assertSame(hash('sha256', '%PDF-1.4 signed-letter'), $file->sha256);
        $this->assertStringStartsWith('request-supporting-documents/', $file->storage_path);
        $this->assertSame('%PDF-1.4 signed-letter', Storage::disk('s3')->get($file->storage_path));
    }

    /* ------------------------------------------------------------------ */
    /* Failures                                                            */
    /* ------------------------------------------------------------------ */

    public function test_a_write_the_disk_reports_as_failed_throws_and_creates_no_record(): void
    {
        config(['filesystems.protected_disk' => 's3']);

        /* A disk configured with throw => false signals failure by returning false. */
        $failing = Mockery::mock(Filesystem::class);
        $failing->shouldReceive('put')->once()->andReturn(false);
        Storage::set('s3', $failing);

        try {
            $this->files->storeBytes('%PDF-1.4', 'generated-documents', 'slip.pdf', 'application/pdf', 'pdf');
            $this->fail('A failed write must not pass silently.');
        } catch (ProtectedFileStorageException $exception) {
            $this->assertStringContainsString('[s3]', $exception->getMessage());
        }

        $this->assertSame(0, StoredFile::query()->count());
    }

    public function test_a_write_that_raises_is_reported_as_a_storage_failure_and_creates_no_record(): void
    {
        config(['filesystems.protected_disk' => 's3']);

        /* A disk configured with throw => true raises instead. */
        $failing = Mockery::mock(Filesystem::class);
        $failing->shouldReceive('put')->once()->andThrow(new RuntimeException('bucket unreachable'));
        Storage::set('s3', $failing);

        try {
            $this->files->storeBytes('%PDF-1.4', 'generated-documents', 'slip.pdf', 'application/pdf', 'pdf');
            $this->fail('A failed write must not pass silently.');
        } catch (ProtectedFileStorageException $exception) {
            $this->assertStringContainsString('[s3]', $exception->getMessage());
        }

        $this->assertSame(0, StoredFile::query()->count());
    }

    public function test_a_write_that_raises_is_logged_by_type_without_the_library_message_or_trace(): void
    {
        config(['filesystems.protected_disk' => 's3']);

        $bytes = 'CONFIDENTIAL-DOCUMENT-BODY';

        /* A storage service's message can quote its raw response, key id included. */
        $failing = Mockery::mock(Filesystem::class);
        $failing->shouldReceive('put')->once()->andThrow(
            new RuntimeException('403 SignatureDoesNotMatch <AWSAccessKeyId>AKIAEXAMPLEKEYID</AWSAccessKeyId>')
        );
        Storage::set('s3', $failing);

        Log::spy();

        try {
            $this->files->storeBytes($bytes, 'generated-documents', 'slip.pdf', 'application/pdf', 'pdf');
            $this->fail('A failed write must not pass silently.');
        } catch (ProtectedFileStorageException $exception) {
            /* Not chained: the library's trace quotes the start of the bytes. */
            $this->assertNull($exception->getPrevious());
            $this->assertStringNotContainsString('AKIAEXAMPLEKEYID', (string) $exception);
            $this->assertStringNotContainsString('CONFIDENTIAL', (string) $exception);
        }

        Log::shouldHaveReceived('error')->once()->withArgs(function (string $message, array $context) use ($bytes): bool {
            $logged = $message.' '.json_encode($context);

            $this->assertSame('s3', $context['disk']);
            $this->assertStringStartsWith('generated-documents/', $context['storage_path']);
            $this->assertSame(strlen($bytes), $context['byte_size']);
            $this->assertSame([RuntimeException::class], $context['exceptions']);

            $this->assertStringNotContainsString($bytes, $logged);
            $this->assertStringNotContainsString('AKIAEXAMPLEKEYID', $logged);
            $this->assertStringNotContainsString('SignatureDoesNotMatch', $logged);

            return true;
        });
    }

    public function test_a_failed_upload_creates_no_record(): void
    {
        config(['filesystems.protected_disk' => 's3']);

        $failing = Mockery::mock(Filesystem::class);
        $failing->shouldReceive('put')->once()->andReturn(false);
        Storage::set('s3', $failing);

        $this->expectException(ProtectedFileStorageException::class);

        try {
            $this->files->storeUpload(
                UploadedFile::fake()->createWithContent('receipt.pdf', '%PDF-1.4 receipt'),
                'payment-evidence',
                'CSPC_CASHIER_PAID_RECEIPT'
            );
        } finally {
            $this->assertSame(0, StoredFile::query()->count());
        }
    }

    public function test_a_misconfigured_disk_name_fails_loudly_instead_of_writing_elsewhere(): void
    {
        config(['filesystems.protected_disk' => 'no-such-disk']);

        try {
            $this->files->storeBytes('%PDF-1.4', 'generated-documents', 'slip.pdf', 'application/pdf', 'pdf');
            $this->fail('An unknown disk must not fall back silently.');
        } catch (ProtectedFileStorageException) {
            $this->assertSame(0, StoredFile::query()->count());
            $this->assertSame([], Storage::disk('local')->allFiles());
            $this->assertSame([], Storage::disk('s3')->allFiles());
        }
    }

    public function test_a_database_failure_after_the_write_deletes_nothing(): void
    {
        config(['filesystems.protected_disk' => 's3']);

        /* A document that already exists must survive whatever happens next. */
        $existing = $this->files->storeBytes('%PDF-1.4 existing', 'generated-documents', 'existing.pdf', 'application/pdf', 'pdf');

        StoredFile::creating(function (): void {
            throw new RuntimeException('database unavailable');
        });

        try {
            $this->files->storeBytes('%PDF-1.4 new', 'generated-documents', 'new.pdf', 'application/pdf', 'pdf');
            $this->fail('The database failure must reach the caller.');
        } catch (RuntimeException $exception) {
            /* The original failure, not a storage failure: the write itself succeeded. */
            $this->assertSame('database unavailable', $exception->getMessage());
            $this->assertNotInstanceOf(ProtectedFileStorageException::class, $exception);
        } finally {
            StoredFile::flushEventListeners();
        }

        /* No record for the new file, and the existing one untouched. */
        $this->assertSame(1, StoredFile::query()->count());
        Storage::disk('s3')->assertExists($existing->storage_path);
        $this->assertSame('%PDF-1.4 existing', $this->files->bytes($existing));

        /* The object that was written is left in place, not removed automatically. */
        $this->assertCount(2, Storage::disk('s3')->allFiles('generated-documents'));
    }

    public function test_the_orphaned_object_log_entry_names_the_location_and_nothing_confidential(): void
    {
        config(['filesystems.protected_disk' => 's3']);

        $bytes = 'CONFIDENTIAL-DOCUMENT-BODY';

        /* A database error message carries the statement and its values. */
        StoredFile::creating(function (): void {
            throw new RuntimeException('SQLSTATE insert failed: Borrower Medical Letter.pdf password=db-secret');
        });

        Log::spy();

        try {
            $this->files->storeBytes($bytes, 'generated-documents', 'Borrower Medical Letter.pdf', 'application/pdf', 'pdf', 'CONTROLLED_DOCUMENT');
            $this->fail('The database failure must reach the caller.');
        } catch (RuntimeException) {
            /* Expected: the caller still receives the original failure. */
        } finally {
            StoredFile::flushEventListeners();
        }

        Log::shouldHaveReceived('error')->once()->withArgs(function (string $message, array $context) use ($bytes): bool {
            $logged = $message.' '.json_encode($context);

            $this->assertSame(['disk', 'storage_path', 'byte_size', 'sha256', 'classification', 'error'], array_keys($context));
            $this->assertSame('s3', $context['disk']);
            $this->assertStringStartsWith('generated-documents/', $context['storage_path']);
            $this->assertSame(strlen($bytes), $context['byte_size']);
            $this->assertSame(RuntimeException::class, $context['error']);

            $this->assertStringNotContainsString($bytes, $logged);
            $this->assertStringNotContainsString('Borrower Medical Letter', $logged);
            $this->assertStringNotContainsString('db-secret', $logged);
            $this->assertStringNotContainsString('SQLSTATE', $logged);

            return true;
        });
    }

    public function test_a_storage_failure_message_and_trace_do_not_carry_the_document_bytes(): void
    {
        config(['filesystems.protected_disk' => 's3']);

        $bytes = 'CONFIDENTIAL-DOCUMENT-BODY';

        $failing = Mockery::mock(Filesystem::class);
        $failing->shouldReceive('put')->once()->andReturn(false);
        Storage::set('s3', $failing);

        /* Full traces, as in debug mode: the bytes must be redacted even then. */
        $ignoreArgs = ini_set('zend.exception_ignore_args', '0');

        try {
            $this->files->storeBytes($bytes, 'generated-documents', 'slip.pdf', 'application/pdf', 'pdf');
            $this->fail('A failed write must not pass silently.');
        } catch (ProtectedFileStorageException $exception) {
            $this->assertSame('The file could not be written to the [s3] disk.', $exception->getMessage());

            /* What an exception log line is built from: message plus stack trace. */
            $rendered = (string) $exception;

            $this->assertStringNotContainsString('CONFIDENTIAL', $rendered);
            $this->assertStringContainsString('SensitiveParameterValue', $rendered);
        } finally {
            ini_set('zend.exception_ignore_args', (string) $ignoreArgs);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Reading                                                             */
    /* ------------------------------------------------------------------ */

    public function test_each_file_is_read_from_the_disk_its_record_names(): void
    {
        /* Written while the protected disk was local... */
        $onLocal = $this->files->storeBytes('local bytes', 'generated-documents', 'old.pdf', 'application/pdf', 'pdf');

        /* ...then the environment switches to object storage. */
        config(['filesystems.protected_disk' => 's3']);
        $onS3 = $this->files->storeBytes('s3 bytes', 'generated-documents', 'new.pdf', 'application/pdf', 'pdf');

        $this->assertSame('local', $onLocal->fresh()->disk, 'An existing record is never re-pointed.');
        $this->assertSame('local bytes', $this->files->bytes($onLocal->fresh()));
        $this->assertSame('s3 bytes', $this->files->bytes($onS3->fresh()));

        /* And back again: the s3 record still reads from s3. */
        config(['filesystems.protected_disk' => 'local']);
        $this->assertSame('s3 bytes', $this->files->bytes($onS3->fresh()));
        Storage::disk('local')->assertExists($onLocal->storage_path);
        Storage::disk('s3')->assertMissing($onLocal->storage_path);
    }

    /* ------------------------------------------------------------------ */
    /* Profile pictures                                                    */
    /* ------------------------------------------------------------------ */

    public function test_a_profile_picture_is_stored_on_the_local_disk_by_default_and_can_be_viewed(): void
    {
        $user = $this->borrower();

        $this->actingAs($user)
            ->post(route('profile.picture.update'), ['profile_picture' => UploadedFile::fake()->image('me.png', 40, 40)])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Profile picture updated successfully.');

        $path = $user->fresh()->profile_picture_path;

        $this->assertStringStartsWith('profile-pictures/'.$user->id.'/', (string) $path);
        Storage::disk('local')->assertExists($path);
        Storage::disk('s3')->assertMissing($path);

        $this->actingAs($user)->get(route('profile.picture.show'))->assertOk();
    }

    public function test_a_profile_picture_is_stored_on_the_configured_s3_disk_and_can_be_viewed(): void
    {
        config(['filesystems.protected_disk' => 's3']);

        $user = $this->borrower();

        $this->actingAs($user)
            ->post(route('profile.picture.update'), ['profile_picture' => UploadedFile::fake()->image('me.png', 40, 40)])
            ->assertSessionHasNoErrors();

        $path = $user->fresh()->profile_picture_path;

        Storage::disk('s3')->assertExists($path);
        Storage::disk('local')->assertMissing($path);

        $this->actingAs($user)->get(route('profile.picture.show'))->assertOk();
    }

    public function test_a_local_picture_is_still_shown_after_the_protected_disk_becomes_s3(): void
    {
        $user = $this->borrower();
        $path = 'profile-pictures/'.$user->id.'/before-switch.png';

        Storage::disk('local')->put($path, 'local-picture');
        $user->forceFill(['profile_picture_path' => $path])->save();

        /* Nothing copied it to object storage, and nothing may assume so. */
        config(['filesystems.protected_disk' => 's3']);

        $response = $this->actingAs($user)->get(route('profile.picture.show'));

        $response->assertOk();
        $this->assertSame('local-picture', $response->streamedContent());
    }

    public function test_replacing_a_picture_removes_the_previous_one_from_the_disk_it_was_on(): void
    {
        $user = $this->borrower();
        $previous = 'profile-pictures/'.$user->id.'/previous.png';

        Storage::disk('local')->put($previous, 'previous-picture');
        $user->forceFill(['profile_picture_path' => $previous])->save();

        config(['filesystems.protected_disk' => 's3']);

        $this->actingAs($user)
            ->post(route('profile.picture.update'), ['profile_picture' => UploadedFile::fake()->image('new.png', 40, 40)])
            ->assertSessionHasNoErrors();

        $current = $user->fresh()->profile_picture_path;

        $this->assertNotSame($previous, $current);
        Storage::disk('s3')->assertExists($current);
        /* The same replace behaviour as before: the old picture goes, from where it actually was. */
        Storage::disk('local')->assertMissing($previous);
    }

    public function test_removing_a_picture_clears_the_profile_and_deletes_the_file(): void
    {
        $user = $this->borrower();
        $path = 'profile-pictures/'.$user->id.'/current.png';

        Storage::disk('local')->put($path, 'current-picture');
        $user->forceFill(['profile_picture_path' => $path])->save();

        $this->actingAs($user)
            ->delete(route('profile.picture.destroy'))
            ->assertSessionHas('status', 'Profile picture removed successfully.');

        $this->assertNull($user->fresh()->profile_picture_path);
        Storage::disk('local')->assertMissing($path);

        $this->actingAs($user)->get(route('profile.picture.show'))->assertNotFound();
    }

    public function test_a_picture_that_cannot_be_written_is_not_saved_to_the_profile(): void
    {
        config(['filesystems.protected_disk' => 's3']);

        $user = $this->borrower();
        $previous = 'profile-pictures/'.$user->id.'/kept.png';

        Storage::disk('local')->put($previous, 'kept-picture');
        $user->forceFill(['profile_picture_path' => $previous])->save();

        $failing = Mockery::mock(Filesystem::class);
        $failing->shouldReceive('exists')->andReturn(false);
        $failing->shouldReceive('putFileAs')->andReturn(false);
        Storage::set('s3', $failing);

        $this->actingAs($user)
            ->post(route('profile.picture.update'), ['profile_picture' => UploadedFile::fake()->image('new.png', 40, 40)])
            ->assertSessionHasErrors('profile_picture');

        /* The profile and the existing picture are exactly as they were. */
        $this->assertSame($previous, $user->fresh()->profile_picture_path);
        Storage::disk('local')->assertExists($previous);
    }

    public function test_another_borrower_cannot_load_someone_elses_picture(): void
    {
        $owner = $this->borrower();
        $path = 'profile-pictures/'.$owner->id.'/private.png';

        Storage::disk('local')->put($path, 'private-picture');
        $owner->forceFill(['profile_picture_path' => $path])->save();

        $this->actingAs($this->otherBorrower())
            ->get(route('users.picture.show', $owner))
            ->assertForbidden();
    }

    /* ------------------------------------------------------------------ */
    /* Document access, preview and download                               */
    /* ------------------------------------------------------------------ */

    public function test_a_generated_document_on_s3_downloads_views_and_previews_for_its_borrower(): void
    {
        config(['filesystems.protected_disk' => 's3']);

        $borrower = $this->borrower();
        $bytes = '%PDF-1.4 borrower-slip';
        $document = $this->generatedDocument($borrower, $bytes);

        $this->assertSame('s3', $document->file->disk);

        $download = $this->actingAs($borrower)->get(route('documents.download', $document));
        $download->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame($bytes, $download->streamedContent());
        $this->assertStringContainsString('attachment', (string) $download->headers->get('Content-Disposition'));
        $this->assertSame($document->file->sha256, hash('sha256', $download->streamedContent()));

        $view = $this->actingAs($borrower)->get(route('documents.view', $document));
        $view->assertOk();
        $this->assertSame($bytes, $view->streamedContent());
        $this->assertStringContainsString('inline', (string) $view->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', (string) $view->headers->get('Cache-Control'));

        $this->actingAs($borrower)
            ->get(route('documents.preview', $document))
            ->assertOk()
            ->assertViewIs('documents.preview');
    }

    public function test_a_generated_document_on_s3_is_refused_to_other_borrowers_and_to_ictu(): void
    {
        config(['filesystems.protected_disk' => 's3']);

        $document = $this->generatedDocument($this->borrower(), '%PDF-1.4 borrower-slip');

        foreach ([$this->otherBorrower(), $this->ictu()] as $outsider) {
            foreach (['documents.download', 'documents.view', 'documents.preview'] as $route) {
                $this->actingAs($outsider)->get(route($route, $document))->assertForbidden();
            }
        }
    }

    public function test_spmu_can_open_a_generated_document_stored_on_s3(): void
    {
        config(['filesystems.protected_disk' => 's3']);

        $document = $this->generatedDocument($this->borrower(), '%PDF-1.4 borrower-slip');

        $response = $this->withSession(['active_workspace' => 'SPMU'])
            ->actingAs($this->spmuHead())
            ->get(route('documents.view', $document));

        $response->assertOk();
        $this->assertSame('%PDF-1.4 borrower-slip', $response->streamedContent());
    }

    public function test_a_superseded_document_stays_unavailable_whatever_disk_it_is_on(): void
    {
        config(['filesystems.protected_disk' => 's3']);

        $borrower = $this->borrower();
        $document = $this->generatedDocument($borrower, '%PDF-1.4 old-slip', 'SUPERSEDED');

        $this->actingAs($borrower)->get(route('documents.view', $document))->assertStatus(410);
    }

    public function test_an_uploaded_document_on_s3_opens_for_its_owner_and_spmu_only(): void
    {
        config(['filesystems.protected_disk' => 's3']);

        $borrower = $this->borrower();
        $bytes = '%PDF-1.4 signed-request-letter';
        $file = $this->supportingDocument($borrower, $bytes);

        $this->assertSame('s3', $file->disk);

        $own = $this->actingAs($borrower)->get(route('files.show', $file));
        $own->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame($bytes, $own->streamedContent());

        $this->actingAs($borrower)
            ->get(route('files.preview', $file))
            ->assertOk()
            ->assertViewIs('documents.file-preview');

        $spmu = $this->withSession(['active_workspace' => 'SPMU'])
            ->actingAs($this->spmuHead())
            ->get(route('files.show', $file));
        $spmu->assertOk();
        $this->assertSame($bytes, $spmu->streamedContent());

        foreach ([$this->otherBorrower(), $this->ictu()] as $outsider) {
            $this->actingAs($outsider)->get(route('files.show', $file))->assertForbidden();
            $this->actingAs($outsider)->get(route('files.preview', $file))->assertForbidden();
        }
    }

    public function test_a_local_document_still_opens_after_the_protected_disk_becomes_s3(): void
    {
        $borrower = $this->borrower();
        $document = $this->generatedDocument($borrower, '%PDF-1.4 written-locally');

        $this->assertSame('local', $document->file->disk);

        config(['filesystems.protected_disk' => 's3']);

        $response = $this->actingAs($borrower)->get(route('documents.download', $document));

        $response->assertOk();
        $this->assertSame('%PDF-1.4 written-locally', $response->streamedContent());
    }

    public function test_a_guest_is_never_served_a_protected_file(): void
    {
        config(['filesystems.protected_disk' => 's3']);

        $borrower = $this->borrower();
        $document = $this->generatedDocument($borrower, '%PDF-1.4 borrower-slip');
        $file = $this->supportingDocument($borrower, '%PDF-1.4 letter');

        auth()->logout();

        $this->get(route('documents.download', $document))->assertRedirect();
        $this->get(route('files.show', $file))->assertRedirect();
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
        $template = $this->borrower();

        return User::factory()->create([
            'access_classification' => AccessClassification::BorrowerOnly,
            'organizational_unit_id' => $template->organizational_unit_id,
        ]);
    }

    private function requestVersion(User $borrower): RequestVersion
    {
        $request = BorrowingRequest::query()->create([
            'request_no' => 'BR-STORAGE-'.fake()->unique()->numberBetween(1000, 9999),
            'borrower_user_id' => $borrower->id,
            'accountable_unit_id' => $borrower->organizational_unit_id,
            'current_version_no' => 1,
            'status' => RequestStatus::UnderSpmu,
        ]);

        return $request->versions()->create([
            'version_no' => 1,
            'purpose_event' => 'Protected storage test',
            'location' => 'CSPC Campus',
            'needed_from' => now()->addDay(),
            'return_due_at' => now()->addDays(2),
            'created_by_user_id' => $borrower->id,
        ]);
    }

    /** A controlled document generated for the borrower, stored through the service. */
    private function generatedDocument(User $borrower, string $bytes, string $status = 'FINAL'): GeneratedDocument
    {
        $version = $this->requestVersion($borrower);

        $file = $this->files->storeBytes($bytes, 'generated-documents', 'borrower-slip.pdf', 'application/pdf', 'pdf', 'CONTROLLED_DOCUMENT', $borrower->id);

        return GeneratedDocument::query()->create([
            'stored_file_id' => $file->id,
            'request_version_id' => $version->id,
            'subject_type' => BorrowingRequest::class,
            'subject_id' => $version->request_id,
            'document_no' => 'DOC-STORAGE-'.fake()->unique()->numberBetween(1000, 9999),
            'document_type' => 'BORROWER_SLIP',
            'version_no' => 1,
            'sha256' => $file->sha256,
            'status' => $status,
            'generated_at' => now(),
        ])->load('file');
    }

    /** The borrower's current signed request letter, stored through the service. */
    private function supportingDocument(User $borrower, string $bytes): StoredFile
    {
        $version = $this->requestVersion($borrower);

        $file = $this->files->storeBytes($bytes, 'request-supporting-documents', 'request-letter.pdf', 'application/pdf', 'pdf', 'REQUEST_SUPPORTING_DOCUMENT', $borrower->id);

        RequestSupportingDocument::query()->create([
            'request_id' => $version->request_id,
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
}
