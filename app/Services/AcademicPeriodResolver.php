<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

// This is the date-based academic calendar used by Configuration, Reports and Analytics.
class AcademicPeriodResolver
{
    public function day(CarbonInterface|string|null $date = null): CarbonImmutable
    {
        $instant = $date instanceof CarbonInterface
            ? CarbonImmutable::instance($date)
            : CarbonImmutable::parse($date ?? 'now', 'Asia/Manila');

        return $instant->setTimezone('Asia/Manila')->startOfDay();
    }

    public function statusFor(AcademicPeriod $period, CarbonInterface|string|null $date = null): string
    {
        $day = $this->day($date)->toDateString();

        return match (true) {
            $day < $period->start_date->toDateString() => 'UPCOMING',
            $day > $period->end_date->toDateString() => 'COMPLETED',
            default => 'ACTIVE',
        };
    }

    /** @return Collection<int, AcademicPeriod> */
    public function covering(CarbonInterface|string|null $date = null, ?Collection $periods = null): Collection
    {
        $day = $this->day($date)->toDateString();

        if ($periods === null) {
            return AcademicPeriod::query()->whereDate('start_date', '<=', $day)
                ->whereDate('end_date', '>=', $day)->get();
        }

        return $periods->filter(fn (AcademicPeriod $period): bool => $day >= $period->start_date->toDateString()
            && $day <= $period->end_date->toDateString())->values();
    }

    public function current(CarbonInterface|string|null $date = null, ?Collection $periods = null): ?AcademicPeriod
    {
        $matches = $this->covering($date, $periods);

        return $matches->count() === 1 ? $matches->first() : null;
    }

    public function academicYear(CarbonInterface|string|null $date = null, ?Collection $periods = null): ?string
    {
        $day = $this->day($date)->toDateString();
        $years = ($periods ?? AcademicPeriod::query()->get())->groupBy('academic_year')
            ->filter(fn (Collection $terms): bool => $day >= $terms->min(fn ($term) => $term->start_date->toDateString())
                && $day <= $terms->max(fn ($term) => $term->end_date->toDateString()));

        return $years->count() === 1 ? (string) $years->keys()->first() : null;
    }

    // This is the validation for semester boundaries and unambiguous academic-year coverage.
    public function validateCalendar(array $data, ?AcademicPeriod $existing = null): void
    {
        $others = AcademicPeriod::query()->when($existing, fn ($query) => $query->whereKeyNot($existing->id))
            ->lockForUpdate()->get();
        if ($others->contains(fn ($period) => $period->academic_year === $data['academic_year']
            && $period->term_code === $data['term_code'])) {
            throw ValidationException::withMessages(['term_code' => 'This semester is already configured for that academic year.']);
        }

        if ($others->contains(fn ($period) => $data['start_date'] <= $period->end_date->toDateString()
            && $data['end_date'] >= $period->start_date->toDateString())) {
            throw ValidationException::withMessages(['start_date' => 'These dates overlap another semester. Each date must belong to only one semester.']);
        }

        $sameYear = $others->where('academic_year', $data['academic_year']);
        $from = min($data['start_date'], $sameYear->min(fn ($term) => $term->start_date->toDateString()) ?? $data['start_date']);
        $to = max($data['end_date'], $sameYear->max(fn ($term) => $term->end_date->toDateString()) ?? $data['end_date']);
        $overlappingYear = $others->where('academic_year', '!=', $data['academic_year'])->groupBy('academic_year')
            ->contains(fn (Collection $terms): bool => $from <= $terms->max(fn ($term) => $term->end_date->toDateString())
                && $to >= $terms->min(fn ($term) => $term->start_date->toDateString()));
        if ($overlappingYear) {
            throw ValidationException::withMessages(['academic_year' => 'The configured academic-year date ranges must not overlap. Check the semester dates and academic year.']);
        }
    }
}
