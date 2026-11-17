<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeInterface;
use InvalidArgumentException;

class EthiopianDateService
{
    private const ETHIOPIC_MONTHS = [
        1 => 'መስከረም',
        2 => 'ጥቅምት',
        3 => 'ህዳር',
        4 => 'ታህሳስ',
        5 => 'ጥር',
        6 => 'የካቲት',
        7 => 'መጋቢት',
        8 => 'ሚያዝያ',
        9 => 'ግንቦት',
        10 => 'ሰኔ',
        11 => 'ሐምሌ',
        12 => 'ነሐሴ',
        13 => 'ጳጉሜ',
    ];

    /**
     * Convert a Gregorian Date (year, month, day) to Ethiopian calendar values.
     *
     * @return array{year: int, month: int, day: int, month_name: string, formatted: string, formatted_long: string}
     */
    public static function fromGregorian(int $year, int $month, int $day): array
    {
        $jdn = gregoriantojd($month, $day, $year);

        // Gregorian to Ethiopic conversion via Julian Day Number (epoch 1723856)
        $r = ($jdn - 1723856) % 1461;
        $n = ($r % 365) + 365 * intdiv($r, 1460);

        $ethYear = 4 * intdiv($jdn - 1723856, 1461) + intdiv($r, 365) - intdiv($r, 1460);
        $ethMonth = intdiv($n, 30) + 1;
        $ethDay = ($n % 30) + 1;

        $monthName = self::ETHIOPIC_MONTHS[$ethMonth] ?? '';
        $dayPadded = str_pad((string) $ethDay, 2, '0', STR_PAD_LEFT);
        $monthPadded = str_pad((string) $ethMonth, 2, '0', STR_PAD_LEFT);

        return [
            'year' => $ethYear,
            'month' => $ethMonth,
            'day' => $ethDay,
            'month_name' => $monthName,
            'formatted' => "{$dayPadded}/{$monthPadded}/{$ethYear} ዓ.ም",
            'formatted_long' => "{$monthName} {$ethDay} ቀን {$ethYear} ዓ.ም",
        ];
    }

    /**
     * Convert an Ethiopian calendar date to Gregorian date values.
     *
     * @return array{year: int, month: int, day: int, formatted: string}
     */
    public static function toGregorian(int $ethYear, int $ethMonth, int $ethDay): array
    {
        if ($ethMonth < 1 || $ethMonth > 13) {
            throw new InvalidArgumentException("Invalid Ethiopian month: {$ethMonth}. Must be between 1 and 13.");
        }

        $maxDays = ($ethMonth === 13) ? (($ethYear % 4 === 3) ? 6 : 5) : 30;
        if ($ethDay < 1 || $ethDay > $maxDays) {
            throw new InvalidArgumentException("Invalid Ethiopian day: {$ethDay} for month {$ethMonth} of year {$ethYear}. Maximum is {$maxDays}.");
        }

        // Exact bijection formula to Julian Day Number
        $jdn = 1723856 + 365 * $ethYear + intdiv($ethYear, 4) + 30 * ($ethMonth - 1) + $ethDay - 1;

        if (function_exists('cal_from_jd')) {
            $greg = cal_from_jd($jdn, CAL_GREGORIAN);
            $year = (int) $greg['year'];
            $month = (int) $greg['month'];
            $day = (int) $greg['day'];
        } else {
            // Pure integer arithmetic fallback (Fliegel-Van Flandern)
            $l = $jdn + 68569;
            $n = intdiv(4 * $l, 146097);
            $l = $l - intdiv(146097 * $n + 3, 4);
            $i = intdiv(4000 * ($l + 1), 1461001);
            $l = $l - intdiv(1461 * $i, 4) + 31;
            $j = intdiv(80 * $l, 2447);
            $day = $l - intdiv(2447 * $j, 80);
            $l = intdiv($j, 11);
            $month = $j + 2 - 12 * $l;
            $year = 100 * ($n - 49) + $i + $l;
        }

        $dayPadded = str_pad((string) $day, 2, '0', STR_PAD_LEFT);
        $monthPadded = str_pad((string) $month, 2, '0', STR_PAD_LEFT);

        return [
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'formatted' => "{$year}-{$monthPadded}-{$dayPadded}",
        ];
    }

    /**
     * Parse a formatted Ethiopian date string (e.g. '18/01/2019', '18/01/2019 ዓ.ም', '20/12/2018')
     * and convert it to Gregorian date string (YYYY-MM-DD).
     */
    public static function parseAndConvertToGregorian(?string $ethiopianDateStr): ?string
    {
        if (! $ethiopianDateStr) {
            return null;
        }

        // Normalize string: strip "ዓ.ም", "9.9", spaces
        $cleaned = trim(preg_replace('/(ዓ\.?ም\.?|[0-9]\.[0-9]|E\.?C\.?)/u', '', $ethiopianDateStr) ?? '');
        $cleaned = trim($cleaned);

        // Match DD/MM/YYYY or DD-MM-YYYY
        if (preg_match('/^(\d{1,2})[\/\.-](\d{1,2})[\/\.-](\d{4})$/', $cleaned, $matches)) {
            $day = (int) $matches[1];
            $month = (int) $matches[2];
            $year = (int) $matches[3];

            try {
                return self::toGregorian($year, $month, $day)['formatted'];
            } catch (\Throwable) {
                return null;
            }
        }

        // Match YYYY-MM-DD (already Gregorian or Ethiopic in YYYY-MM-DD order)
        if (preg_match('/^(\d{4})[\/\.-](\d{1,2})[\/\.-](\d{1,2})$/', $cleaned, $matches)) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            $day = (int) $matches[3];

            // If year is typical Ethiopic (1900-2100) and month is <= 13
            if ($year < 2050 && $month <= 13 && $day <= 30) {
                try {
                    return self::toGregorian($year, $month, $day)['formatted'];
                } catch (\Throwable) {
                    return null;
                }
            }
        }

        return null;
    }

    /**
     * Format a string date (e.g. '2026-09-28' or ISO) to Ethiopian calendar display.
     */
    public static function formatFromDateString(?string $dateStr): string
    {
        if (! $dateStr) {
            $now = new \DateTimeImmutable();
            return self::fromGregorian((int) $now->format('Y'), (int) $now->format('n'), (int) $now->format('j'))['formatted'];
        }

        try {
            $dt = new \DateTimeImmutable($dateStr);
            return self::fromGregorian((int) $dt->format('Y'), (int) $dt->format('n'), (int) $dt->format('j'))['formatted'];
        } catch (\Throwable) {
            return $dateStr;
        }
    }
}
