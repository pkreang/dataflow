<?php

namespace App\Support;

use App\Models\Holiday;
use Illuminate\Support\Facades\Cache;

/**
 * Org-wide holiday calendar math. "Workday" here means a calendar day that is
 * not an active holiday — weekends are NOT skipped by design (the org may run
 * 6-7 day weeks; per-user work_days live on shift schedules instead).
 */
class WorkdayCalculator
{
    public const CACHE_KEY = 'holidays_active_dates';

    /** @return string[] sorted active holiday dates as 'Y-m-d' */
    public function activeDates(): array
    {
        return Cache::remember(self::CACHE_KEY, 3600, fn () => Holiday::query()
            ->where('is_active', true)
            ->orderBy('date')
            ->pluck('date')
            ->map(fn ($d) => $d->format('Y-m-d'))
            ->all());
    }

    public function isHoliday(string $date): bool
    {
        return in_array($date, $this->activeDates(), true);
    }

    /**
     * Inclusive day count between two dates minus active holidays in range.
     * Mirrors FormulaEvaluator DAYS() semantics (absolute, inclusive).
     */
    public function workdaysBetween(string $from, string $to): int
    {
        $d1 = new \DateTimeImmutable($from);
        $d2 = new \DateTimeImmutable($to);
        if ($d1 > $d2) {
            [$d1, $d2] = [$d2, $d1];
        }

        $total = $d1->diff($d2)->days + 1;
        $lo = $d1->format('Y-m-d');
        $hi = $d2->format('Y-m-d');
        $holidays = count(array_filter(
            $this->activeDates(),
            fn (string $d) => $d >= $lo && $d <= $hi
        ));

        return max(0, $total - $holidays);
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
