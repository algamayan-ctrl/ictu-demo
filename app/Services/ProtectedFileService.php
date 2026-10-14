<?php

namespace App\Services;

use App\Exceptions\ProtectedFileStorageException;
use App\Models\StoredFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ProtectedFileService
{
    /**
     * The disk new protected files are written to.
     *
     * Configured by PROTECTED_FILES_DISK and "local" unless an environment
     * says otherwise. Only writes use it: every read follows the disk
     * recorded on the stored_files row, so files written before a change of
     * this value stay where they are and stay readable.
     */
    public function writeDisk(): string
    {
        // Both branches used different names. Accept either configured private
        // disk, but never silently choose between conflicting non-default values.
        $incoming = trim((string) config('filesystems.protected', 'local')) ?: 'local';
        $main = trim((string) config('filesystems.protected_disk', 'local')) ?: 'local';
        if ($incoming !== 'local' && $main !== 'local' && $incoming !== $main) {
            throw new InvalidArgumentException('Conflicting protected storage disks are configured.');
        }

        return $incoming !== 'local' ? $incoming : $main;
    }

    public function storeUpload(UploadedFile $upload, string $folder, string $classification = 'PROTECTED'): StoredFile
    {
        $bytes = file_get_contents($upload->getRealPath());

        /* An unreadable upload must not be stored as an empty file. */
        if ($bytes === false) {
            throw new ProtectedFileStorageException('The uploaded file could not be read.');
        }

        $extension = strtolower($upload->guessExtension() ?: $upload->getClientOriginalExtension() ?: 'bin');

        return $this->storeBytes($bytes, $folder, $upload->getClientOriginalName(), $upload->getMimeType() ?: 'application/octet-stream', $extension, $classification);
    }

    public function storeBytes(#[\SensitiveParameter] string $bytes, string $folder, #[\SensitiveParameter] string $originalName, string $mimeType, string $extension, string $classification = 'PROTECTED', ?int $uploaderId = null): StoredFile
    {
        $folder = trim($folder, '/');
        $extension = ltrim($extension, '.');
        if (! preg_match('~\A[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*\z~', $folder)
            || ! preg_match('/\A[a-zA-Z0-9]+\z/', $extension)) {
            throw new InvalidArgumentException('Invalid protected file location.');
        }
        $diskName = $this->writeDisk();
        $diskConfig = config('filesystems.disks.'.$diskName);
        if (! is_array($diskConfig)) {
            throw new ProtectedFileStorageException('The configured protected storage disk is unavailable.');
        }
        if ($diskName === 'public' || ($diskConfig['visibility'] ?? 'private') !== 'private') {
            throw new InvalidArgumentException('Protected evidence requires a configured private disk.');
        }
        $path = $folder.'/'.Str::uuid().'.'.$extension;
        $disk = Storage::disk($diskName);
        $this->write($diskName, $path, $bytes);
        try {
            if (DB::transactionLevel() > 0) {
                // Capture only this newly allocated UUID path, never an existing file.
                // Laravel carries nested callbacks to the outer transaction rollback.
                DB::afterRollBack(fn () => $this->cleanup($diskName, $path));
            }

            return StoredFile::query()->create([
                'uploaded_by_user_id' => $uploaderId ?? auth()->id(),
                'disk' => $diskName,
                'storage_path' => $path,
                'original_name' => $originalName,
                'mime_type' => $mimeType,
                'byte_size' => strlen($bytes),
                'sha256' => hash('sha256', $bytes),
                'classification' => $classification,
            ]);
        } catch (Throwable $exception) {
            Log::error('Protected file metadata could not be saved; cleaning up the new object.', [
                'disk' => $diskName,
                'storage_path' => $path,
                'byte_size' => strlen($bytes),
                'sha256' => hash('sha256', $bytes),
                'classification' => $classification,
                'error' => $exception::class,
            ]);
            $this->cleanup($diskName, $path);
            throw $exception;
        }
    }

    public function bytes(StoredFile $file): string
    {
        $bytes = Storage::disk($file->disk)->get($file->storage_path);
        if (! is_string($bytes) || strlen($bytes) !== (int) $file->byte_size
            || ! hash_equals((string) $file->sha256, hash('sha256', $bytes))) {
            throw new RuntimeException('Protected file is missing or its integrity check failed.');
        }

        return $bytes;
    }

    /**
     * Write the bytes, or throw.
     *
     * A disk configured with throw => false reports failure by returning
     * false; one configured with throw => true raises. Both end in the same
     * exception, raised before any record is created.
     *
     * The storage library's own exception is summarized in the log and not
     * chained: its stack trace quotes the start of the bytes being written,
     * and its message can quote the storage service's raw response.
     */
    private function write(string $disk, string $path, #[\SensitiveParameter] string $bytes): void
    {
        try {
            $written = Storage::disk($disk)->put($path, $bytes, ['visibility' => 'private']);
        } catch (Throwable $exception) {
            $this->cleanup($disk, $path);
            Log::error('Protected file could not be written.', [
                'disk' => $disk,
                'storage_path' => $path,
                'byte_size' => strlen($bytes),
            ] + $this->failureSummary($exception));

            throw new ProtectedFileStorageException("The file could not be written to the [{$disk}] disk.");
        }

        if ($written === false) {
            $this->cleanup($disk, $path);
            throw new ProtectedFileStorageException("The file could not be written to the [{$disk}] disk.");
        }
    }

    /** Only a fresh UUID allocated by this write may be cleaned up. */
    private function cleanup(string $disk, string $path): void
    {
        try {
            if (! Storage::disk($disk)->delete($path)) {
                Log::warning('New protected object cleanup was not confirmed.', ['disk' => $disk, 'storage_path' => $path]);
            }
        } catch (Throwable $exception) {
            // Preserve the original write/database failure; no raw provider response.
            Log::warning('New protected object cleanup failed.', ['disk' => $disk, 'storage_path' => $path, 'error' => $exception::class]);
        }
    }

    /**
     * What may be logged about a storage failure: the exception types and,
     * when the storage service reported them, its error code and HTTP status.
     *
     * @return array{exceptions: list<class-string>, service_error_code: ?string, http_status: ?int}
     */
    private function failureSummary(Throwable $exception): array
    {
        $types = [];
        $errorCode = null;
        $status = null;

        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            $types[] = $current::class;

            if (method_exists($current, 'getAwsErrorCode')) {
                $errorCode ??= $current->getAwsErrorCode();
                $status ??= $current->getStatusCode();
            }
        }

        return [
            'exceptions' => $types,
            'service_error_code' => $errorCode !== null ? (string) $errorCode : null,
            'http_status' => $status !== null ? (int) $status : null,
        ];
    }
}
