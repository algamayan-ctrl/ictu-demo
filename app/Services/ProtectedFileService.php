<?php

namespace App\Services;

use App\Models\StoredFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use League\Flysystem\UnableToWriteFile;
use RuntimeException;
use Throwable;

class ProtectedFileService
{
    public function storeUpload(UploadedFile $upload, string $folder, string $classification = 'PROTECTED'): StoredFile
    {
        $bytes = file_get_contents($upload->getRealPath());
        $extension = strtolower($upload->guessExtension() ?: $upload->getClientOriginalExtension() ?: 'bin');

        return $this->storeBytes($bytes, $folder, $upload->getClientOriginalName(), $upload->getMimeType() ?: 'application/octet-stream', $extension, $classification);
    }

    public function storeBytes(string $bytes, string $folder, string $originalName, string $mimeType, string $extension, string $classification = 'PROTECTED', ?int $uploaderId = null): StoredFile
    {
        $folder = trim($folder, '/');
        $extension = ltrim($extension, '.');
        if (! preg_match('~\A[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*\z~', $folder)
            || ! preg_match('/\A[a-zA-Z0-9]+\z/', $extension)) {
            throw new InvalidArgumentException('Invalid protected file location.');
        }
        $diskName = (string) config('filesystems.protected', 'local');
        $diskConfig = config('filesystems.disks.'.$diskName);
        if (! is_array($diskConfig) || $diskName === 'public' || ($diskConfig['visibility'] ?? 'private') !== 'private') {
            throw new InvalidArgumentException('Protected evidence requires a configured private disk.');
        }
        $path = $folder.'/'.Str::uuid().'.'.$extension;
        $disk = Storage::disk($diskName);
        try {
            if (! $disk->put($path, $bytes, ['visibility' => 'private'])) {
                throw UnableToWriteFile::atLocation($path, 'The private file could not be stored.');
            }
            if (DB::transactionLevel() > 0) {
                // Capture only this newly allocated UUID path, never an existing file.
                // Laravel carries nested callbacks to the outer transaction rollback.
                DB::afterRollBack(fn () => $disk->delete($path));
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
            $disk->delete($path);
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
}
