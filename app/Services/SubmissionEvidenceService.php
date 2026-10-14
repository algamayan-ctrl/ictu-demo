<?php

namespace App\Services;

use App\Models\StoredFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Throwable;

class SubmissionEvidenceService
{
    public function __construct(private ProtectedFileService $files) {}

    public function validateUpload(UploadedFile $upload, string $field): void
    {
        try {
            if (mb_strlen($upload->getClientOriginalName()) > 255) {
                throw new \RuntimeException('Filename exceeds metadata capacity.');
            }
            $this->assertReadable(file_get_contents($upload->getRealPath()), $upload->getMimeType());
        } catch (Throwable) {
            throw ValidationException::withMessages([$field => 'Upload a readable PDF, PNG, JPG, JPEG, or WebP document with a filename of 255 characters or fewer.']);
        }
    }

    public function verifiedBytes(?StoredFile $file, string $field, bool $imageOnly = false): string
    {
        try {
            if (! $file || $file->disk !== 'local') {
                throw new \RuntimeException('Private evidence is missing.');
            }
            $bytes = $this->files->bytes($file);
            if ($bytes === '' || strlen($bytes) !== (int) $file->byte_size
                || ! hash_equals((string) $file->sha256, hash('sha256', $bytes))) {
                throw new \RuntimeException('Evidence integrity mismatch.');
            }
            $this->assertReadable($bytes, $file->mime_type, $imageOnly);

            return $bytes;
        } catch (Throwable) {
            throw ValidationException::withMessages([$field => $imageOnly
                ? 'Your registered E-signature is missing, unreadable, or changed. Register a valid signature again before signing.'
                : 'The supporting document is missing, unreadable, or changed. Upload a valid document again before submitting.']);
        }
    }

    private function assertReadable(string $bytes, ?string $mime, bool $imageOnly = false): void
    {
        if (! $imageOnly && $mime === 'application/pdf') {
            $pdf = new Fpdi;
            $pages = $pdf->setSourceFile(StreamReader::createByString($bytes));
            if ($pages < 1) {
                throw new \RuntimeException('PDF has no pages.');
            }
            // Check every page rather than accepting only a valid PDF header.
            for ($page = 1; $page <= $pages; $page++) {
                $pdf->importPage($page);
            }

            return;
        }

        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            throw new \RuntimeException('Unsupported evidence type.');
        }
        $info = @getimagesizefromstring($bytes);
        $image = @imagecreatefromstring($bytes);
        if (! $image || ! $info || $info['mime'] !== $mime) {
            throw new \RuntimeException('Image cannot be decoded.');
        }
        imagedestroy($image);
    }
}
