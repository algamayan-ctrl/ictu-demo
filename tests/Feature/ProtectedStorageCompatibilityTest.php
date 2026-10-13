<?php

namespace Tests\Feature;

use App\Models\StoredFile;
use App\Services\ProtectedFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class ProtectedStorageCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_configured_private_disk_is_persisted_and_read_without_local_path_assumptions(): void
    {
        config(['filesystems.disks.private-cloud' => ['driver' => 'local', 'visibility' => 'private']]);
        $adapter = Storage::fake('private-cloud');
        $spy = \Mockery::mock($adapter);
        $spy->shouldReceive('put')->once()->withArgs(fn ($path, $bytes, $options) => str_starts_with($path, 'evidence/')
            && $bytes === 'original evidence' && $options === ['visibility' => 'private'])
            ->andReturnUsing(fn ($path, $bytes, $options) => $adapter->put($path, $bytes, $options));
        Storage::set('private-cloud', $spy);
        config(['filesystems.protected' => 'private-cloud']);
        $file = app(ProtectedFileService::class)->storeBytes('original evidence', 'evidence', 'evidence.txt', 'text/plain', 'txt');

        $this->assertSame('private-cloud', $file->disk);
        // Windows local permissions cannot certify a cloud bucket ACL.
        // The adapter spy verifies the private-write contract above.
        $this->assertSame('original evidence', app(ProtectedFileService::class)->bytes($file));
        Storage::disk('local')->assertMissing($file->storage_path);
    }

    public function test_saved_local_evidence_remains_readable_after_the_configured_disk_changes(): void
    {
        config(['filesystems.disks.private-cloud' => ['driver' => 'local', 'visibility' => 'private']]);
        Storage::fake('local');
        Storage::fake('private-cloud');
        config(['filesystems.protected' => 'local']);
        $file = app(ProtectedFileService::class)->storeBytes('legacy evidence', 'evidence', 'legacy.txt', 'text/plain', 'txt');
        config(['filesystems.protected' => 'private-cloud']);

        $this->assertSame('local', $file->disk);
        $this->assertSame('legacy evidence', app(ProtectedFileService::class)->bytes($file));
    }

    public function test_outer_transaction_rollback_removes_only_new_private_objects(): void
    {
        config(['filesystems.disks.private-cloud' => ['driver' => 'local', 'visibility' => 'private']]);
        Storage::fake('private-cloud');
        config(['filesystems.protected' => 'private-cloud']);
        $original = app(ProtectedFileService::class)->storeBytes('keep me', 'evidence', 'original.txt', 'text/plain', 'txt');
        $path = null;
        try {
            DB::transaction(function () use (&$path): void {
                DB::transaction(function () use (&$path): void {
                    $path = app(ProtectedFileService::class)->storeBytes('new', 'evidence', 'new.txt', 'text/plain', 'txt')->storage_path;
                });
                throw new RuntimeException('forced rollback');
            });
            $this->fail('The forced rollback must escape the transaction.');
        } catch (RuntimeException $exception) {
            $this->assertSame('forced rollback', $exception->getMessage());
        }
        Storage::disk('private-cloud')->assertMissing($path);
        Storage::disk('private-cloud')->assertExists($original->storage_path);
        $this->assertSame(1, StoredFile::query()->count());
        $this->assertSame('keep me', app(ProtectedFileService::class)->bytes($original));
    }

    public function test_a_public_disk_cannot_receive_protected_evidence(): void
    {
        Storage::fake('public');
        config(['filesystems.protected' => 'public']);
        $this->expectException(InvalidArgumentException::class);
        app(ProtectedFileService::class)->storeBytes('ID', 'evidence', 'id.txt', 'text/plain', 'txt');
    }

    public function test_corrupted_evidence_is_rejected_instead_of_served_as_the_original(): void
    {
        Storage::fake('local');
        config(['filesystems.protected' => 'local']);
        $file = app(ProtectedFileService::class)->storeBytes('original', 'evidence', 'original.txt', 'text/plain', 'txt');
        Storage::disk('local')->put($file->storage_path, 'modified');
        $this->expectException(RuntimeException::class);
        app(ProtectedFileService::class)->bytes($file);
    }

    public function test_unsafe_folder_is_rejected_before_any_file_or_metadata_write(): void
    {
        Storage::fake('local');
        $this->expectException(InvalidArgumentException::class);
        app(ProtectedFileService::class)->storeBytes('ID', '../public', 'id.txt', 'text/plain', 'txt');
    }
}
