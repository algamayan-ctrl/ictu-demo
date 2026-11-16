<?php

namespace App\Services;

use App\Models\BorrowingRequest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BorrowingRequestNumberService
{
    public function newNumber(int|string $borrowerId): string
    {
        if (! Schema::hasTable('request_number_counters')) {
            throw ValidationException::withMessages([
                'request_no' => 'Request numbering is not ready on this server. Please contact the system administrator before saving or submitting again.',
            ]);
        }

        // This is the function for the generation of a monthly Borrowing Request number.
        $period = now('Asia/Manila')->format('Y-m');
        $prefix = 'BR-'.$period.'-';

        return DB::transaction(function () use ($period, $prefix): string {
            DB::table('request_number_counters')->insertOrIgnore(['period' => $period, 'last_number' => 0]);
            $counter = DB::table('request_number_counters')->where('period', $period)->lockForUpdate()->first();
            $highest = (int) $counter->last_number;

            foreach (BorrowingRequest::query()->where('request_no', 'like', $prefix.'%')->pluck('request_no') as $number) {
                $suffix = substr($number, strlen($prefix));
                if (ctype_digit($suffix)) {
                    $highest = max($highest, (int) $suffix);
                }
            }

            $next = $highest + 1;
            DB::table('request_number_counters')->where('period', $period)->update(['last_number' => $next]);

            return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        }, 5);
    }

    public function createHeader(array $attributes): BorrowingRequest
    {
        if (app(AcademicYearRequestNumbers::class)->enabled()) {
            return BorrowingRequest::query()->create([...$attributes, 'request_no' => app(AcademicYearRequestNumbers::class)->pendingNumber()]);
        }
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
