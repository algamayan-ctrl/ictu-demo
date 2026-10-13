<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/** Read-only public-facing summaries of the authoritative operational calendar. */
class OperationalAdvisoryService
{
    private const LOOKAHEAD_DAYS = 28;

    public function __construct(private readonly OperationalCalendarService $calendar)
    {
    }

    /**
     * Today's on-site transaction status and the next date-specific change.
     *
     * Routine closed days (the configured weekly schedule, e.g. weekends) are
     * reported only when today is one; they are never announced ahead of
     * time. Upcoming notices are reserved for Special Dates: closures of a
     * normally open day and openings or changed hours on any day.
     *
     * @return array{date:string,tone:string,badge:string,message:string,reason:?string,hours:?string,upcoming:?string,next_available:?string}
     */
    public function snapshot(?CarbonImmutable $referenceDate = null): array
    {
        $now = ($referenceDate ?? CarbonImmutable::now('Asia/Manila'))
            ->setTimezone('Asia/Manila');
        $today = $now->startOfDay();

        // One bounded range fetch: no query per day and no change to availability rules.
        $profiles = $this->calendar->profilesForRange($today, $today->addDays(self::LOOKAHEAD_DAYS));

        /** @var list<array{day:CarbonImmutable,profile:array,allowed:bool,exception:bool}> $days */
        $days = [];
        // Whether each weekday is normally available, read from its weekly-schedule days in range.
        $routine = [];

        for ($offset = 0; $offset <= self::LOOKAHEAD_DAYS; $offset++) {
            $day = $today->addDays($offset);
            $profile = (array) $profiles->get($day->toDateString(), []);
            $allowed = $profile !== [] && $this->physicalAllowed($profile, $day->toDateString());
            $exception = ($profile['source'] ?? null) === 'EXCEPTION';

            if ($profile !== [] && ! $exception) {
                $routine[$day->dayOfWeekIso] ??= $allowed;
            }

            $days[] = ['day' => $day, 'profile' => $profile, 'allowed' => $allowed, 'exception' => $exception];
        }

        $current = $days[0];
        $todayAllowed = $current['allowed'];
        $specialToday = $current['exception'];
        $normallyClosedToday = ($routine[$today->dayOfWeekIso] ?? null) === false;

        // A Special Date closing a day that is closed anyway is still a routine closed day.
        $specialClosure = ! $todayAllowed && $specialToday && ! $normallyClosedToday;

        // A special closure that started today may run for several dates.
        $closureEnd = 0;
        if ($specialClosure) {
            while (isset($days[$closureEnd + 1])
                && $days[$closureEnd + 1]['exception']
                && ! $days[$closureEnd + 1]['allowed']) {
                $closureEnd++;
            }
        }

        // Reuse date-specific profiles; no additional DB fetches.
        $nextAvailable = null;
        foreach (array_slice($days, 1) as $entry) {
            if (! $entry['allowed']) {
                continue;
            }

            [$nextOpen, $nextClose] = $this->calendar->operatingWindow(
                OperationalCalendarService::PICKUP,
                $entry['day']->toDateString(),
                $entry['profile']
            );

            if ($nextOpen !== null && $nextClose !== null && $nextOpen->lt($nextClose)) {
                $nextAvailable = $entry['day']->format('D, M j').' at '.$nextOpen->format('g:i A');
                break;
            }
        }
        // Use the authoritative Operational Calendar hours.
        $beforeOpening = false;
        $afterClosing = false;

        if ($todayAllowed) {
            [$opensAt, $closesAt] = $this->calendar->operatingWindow(
                OperationalCalendarService::PICKUP,
                $today->toDateString(),
                $current['profile']
            );

            $beforeOpening = $opensAt !== null
                && $now->lt($opensAt);

            $afterClosing = $closesAt !== null
                && $now->gte($closesAt);
        }

        $closingTimeLabel = $todayAllowed && $closesAt !== null
            ? $closesAt->format('g:i A')
            : 'the scheduled closing time';
        $nextOpeningNote = $nextAvailable !== null
            ? 'Next on-site opening: '.$nextAvailable.'.'
            : 'Please check the Borrowing Calendar for the next on-site schedule.';

        [$tone, $badge, $message] = match (true) {
            $todayAllowed && $afterClosing => [
                'closed',
                'On-site Closed',
                'On-site transactions have ended for today. '.$nextOpeningNote.' Online borrowing requests remain available.',
            ],
            $todayAllowed && $beforeOpening => [
                'closed',
                'On-site Closed',
                ($specialToday ? 'Special schedule today. ' : '')
                    .'On-site transactions will open today at '.$opensAt->format('g:i A').'. Online borrowing requests remain available.',
            ],
            $todayAllowed && $specialToday => [
                'special',
                'On-site Open',
                ($normallyClosedToday ? 'Special opening today. ' : 'Special schedule today. ')
                    .'On-site transactions are available until '.$closingTimeLabel.'. Online borrowing requests remain available.',
            ],
            $todayAllowed => [
                'normal',
                'On-site Open',
                'On-site transactions are available until '.$closingTimeLabel.'. Online borrowing requests remain available.',
            ],
            $specialClosure => [
                'closed',
                'On-site Closed',
                ($closureEnd > 0
                    ? 'On-site transactions are suspended through '.$days[$closureEnd]['day']->format('D, M j').'. '
                    : 'On-site transactions are suspended today. ')
                    .$nextOpeningNote.' Online borrowing requests remain available.',
            ],
            default => [
                'closed',
                'On-site Closed',
                'No on-site transactions are scheduled today. '.$nextOpeningNote.' Online borrowing requests remain available.',
            ],
        };

        return [
            'date' => $today->toDateString(),
            'tone' => $tone,
            'badge' => $badge,
            'message' => $message,
            'reason' => $specialToday ? $this->publicReason($current['profile']['reason'] ?? null) : null,
            'hours' => $todayAllowed ? $this->hours($current['profile']) : null,
            'upcoming' => $this->upcomingSpecial($days, $closureEnd + 1, $routine),
            'next_available' => ($todayAllowed && ! $afterClosing) ? null : $nextAvailable,
        ];
    }

    /**
     * The first Special Date after today that changes what a borrower can do,
     * with its consecutive dates, hours and reason when the calendar has them.
     *
     * @param  list<array{day:CarbonImmutable,profile:array,allowed:bool,exception:bool}>  $days
     * @param  array<int, bool>  $routine
     */
    private function upcomingSpecial(array $days, int $from, array $routine): ?string
    {
        $kindOf = function (array $entry) use ($routine): ?string {
            if (! $entry['exception']) {
                return null;
            }

            $normallyOpen = $routine[$entry['day']->dayOfWeekIso] ?? null;

            if (! $entry['allowed']) {
                // Closing a day that is closed anyway changes nothing for the borrower.
                return $normallyOpen === false ? null : 'Temporary closure';
            }

            return $normallyOpen === false ? 'Special opening' : 'Special schedule';
        };

        for ($index = max(1, $from); $index < count($days); $index++) {
            $kind = $kindOf($days[$index]);
            if ($kind === null) {
                continue;
            }

            $first = $days[$index];
            $hours = $first['allowed'] ? $this->hours($first['profile']) : null;
            $reason = $this->publicReason($first['profile']['reason'] ?? null);

            // Consecutive dates of the same kind are one notice.
            $last = $index;
            while (isset($days[$last + 1]) && $kindOf($days[$last + 1]) === $kind) {
                $last++;

                $sameHours = ! $days[$last]['allowed'] || $this->hours($days[$last]['profile']) === $hours;
                $hours = $sameHours ? $hours : null;
                $reason = $this->publicReason($days[$last]['profile']['reason'] ?? null) === $reason ? $reason : null;
            }

            $dates = $first['day']->format('D, M j')
                .($last > $index ? ' – '.$days[$last]['day']->format('D, M j') : '');

            return implode(' · ', array_filter([
                $kind,
                $dates,
                $hours,
                $reason !== null ? mb_strimwidth($reason, 0, 120, '…') : null,
            ]));
        }

        return null;
    }

    private function physicalAllowed(array $profile, string $date): bool
    {
        if (! (bool) ($profile['is_open'] ?? false)
            || (! (bool) ($profile['allows_pickup'] ?? false)
                && ! (bool) ($profile['allows_return'] ?? false))) {
            return false;
        }

        // The existing physical-operation rule also requires valid hours.
        // Pass the already loaded profile so no additional DB queries occur.
        [$open, $close] = $this->calendar->operatingWindow(
            OperationalCalendarService::PICKUP, $date, $profile
        );

        return $open !== null && $close !== null && $open->lt($close);
    }

    private function publicReason(mixed $reason): ?string
    {
        $value = trim((string) ($reason ?? ''));
        return $value !== '' ? mb_substr($value, 0, 240) : null;
    }

    private function hours(array $profile): ?string
    {
        $from = (string) ($profile['open_time'] ?? '');
        $to = (string) ($profile['close_time'] ?? '');
        if (! preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]/', $from)
            || ! preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]/', $to)) {
            return null;
        }

        return CarbonImmutable::createFromFormat('!H:i', substr($from, 0, 5), 'Asia/Manila')->format('g:i A')
            .'–'.CarbonImmutable::createFromFormat('!H:i', substr($to, 0, 5), 'Asia/Manila')->format('g:i A');
    }
}
