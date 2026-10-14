<?php

namespace App\Services;

use App\Models\BorrowingRequest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BorrowingRequestNumberService
{
    public function newNumber(int|string $borrowerId): string
    {
        // Ten characters keep even a 20-digit unsigned ID within VARCHAR(50).
        return 'BR-'.now()->format('YmdHis').'-'.$borrowerId.'-'.strtoupper(Str::random(10));
    }

    public function createHeader(array $attributes): BorrowingRequest
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                // Only header insertion is retried, before versions/files/signing.
                // A savepoint isolates a collision inside store's outer transaction.
                return DB::transaction(fn () => BorrowingRequest::query()->create([
                    ...$attributes,
                    'request_no' => $this->newNumber($attributes['borrower_user_id']),
                ]));
            } catch (UniqueConstraintViolationException $exception) {
                $numberCollision = $exception->index === 'borrowing_requests_request_no_unique'
                    || ($exception->index === null && $exception->columns === ['request_no']);

                if (! $numberCollision || $attempt >= 5) {
                    throw $exception;
                }
            }
        }
    }
}
