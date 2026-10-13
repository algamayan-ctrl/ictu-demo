<?php

namespace App\Services;

use App\Exceptions\ProtectedFileStorageException;
use App\Models\StoredFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        $disk = trim((string) config('filesystems.protected_disk', 'local'));

        return $disk !== '' ? $disk : 'local';
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
        $disk = $this->writeDisk();
        $path = trim($folder, '/').'/'.Str::uuid().'.'.ltrim($extension, '.');

        $this->write($disk, $path, $bytes);

        try {
            return StoredFile::query()->create([
                'uploaded_by_user_id' => $uploaderId ?? auth()->id(),
                /* The disk actually written to, so the file is always read back from there. */
                'disk' => $disk,
                'storage_path' => $path,
                'original_name' => $originalName,
                'mime_type' => $mimeType,
                'byte_size' => strlen($bytes),
                'sha256' => hash('sha256', $bytes),
                'classification' => $classification,
            ]);
        } catch (Throwable $exception) {
            /*
             * The object was written but its record was not. Nothing is
             * deleted here: removing objects automatically is how a stored
             * document gets lost, and the path is a fresh UUID that no
             * record points to. The location is logged for reconciliation
             * and the original failure is passed on unchanged.
             *
             * Only the location and the failure type are logged: never the
             * bytes, the file name, or the failure message, which for a
             * database error carries the statement and its values.
             */
            Log::error('Protected file was stored but its record could not be saved.', [
                'disk' => $disk,
                'storage_path' => $path,
                'byte_size' => strlen($bytes),
                'sha256' => hash('sha256', $bytes),
                'classification' => $classification,
                'error' => $exception::class,
            ]);

            throw $exception;
        }
    }

    public function bytes(StoredFile $file): string
    {
        return Storage::disk($file->disk)->get($file->storage_path);
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
            $written = Storage::disk($disk)->put($path, $bytes);
        } catch (Throwable $exception) {
            Log::error('Protected file could not be written.', [
                'disk' => $disk,
                'storage_path' => $path,
                'byte_size' => strlen($bytes),
            ] + $this->failureSummary($exception));

            throw new ProtectedFileStorageException("The file could not be written to the [{$disk}] disk.");
        }

        if ($written === false) {
            throw new ProtectedFileStorageException("The file could not be written to the [{$disk}] disk.");
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
