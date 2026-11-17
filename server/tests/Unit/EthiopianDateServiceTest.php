<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\EthiopianDateService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EthiopianDateServiceTest extends TestCase
{
    public function test_converts_known_dates_to_ethiopian_calendar_accurately(): void
    {
        // Reference Letter 2: September 28, 2026 is Meskerem 18, 2019 E.C.
        $res1 = EthiopianDateService::fromGregorian(2026, 9, 28);
        $this->assertSame(2019, $res1['year']);
        $this->assertSame(1, $res1['month']);
        $this->assertSame(18, $res1['day']);
        $this->assertSame('18/01/2019 ዓ.ም', $res1['formatted']);

        // Reference Letter 1: August 26, 2026 is Nehase 20, 2018 E.C.
        $res2 = EthiopianDateService::fromGregorian(2026, 8, 26);
        $this->assertSame(2018, $res2['year']);
        $this->assertSame(12, $res2['month']);
        $this->assertSame(20, $res2['day']);
        $this->assertSame('20/12/2018 ዓ.ም', $res2['formatted']);

        // Ethiopian New Year 2019: September 11, 2026 is Meskerem 1, 2019 E.C.
        $res3 = EthiopianDateService::fromGregorian(2026, 9, 11);
        $this->assertSame(2019, $res3['year']);
        $this->assertSame(1, $res3['month']);
        $this->assertSame(1, $res3['day']);
        $this->assertSame('01/01/2019 ዓ.ም', $res3['formatted']);
    }

    public function test_converts_ethiopian_to_gregorian_accurately(): void
    {
        // Reference Letter 1: 20/12/2018 E.C. -> 2026-08-26
        $greg1 = EthiopianDateService::toGregorian(2018, 12, 20);
        $this->assertSame(2026, $greg1['year']);
        $this->assertSame(8, $greg1['month']);
        $this->assertSame(26, $greg1['day']);
        $this->assertSame('2026-08-26', $greg1['formatted']);

        // Reference Letter 2: 18/01/2019 E.C. -> 2026-09-28
        $greg2 = EthiopianDateService::toGregorian(2019, 1, 18);
        $this->assertSame(2026, $greg2['year']);
        $this->assertSame(9, $greg2['month']);
        $this->assertSame(28, $greg2['day']);
        $this->assertSame('2026-09-28', $greg2['formatted']);

        // Ethiopian New Year: 01/01/2019 E.C. -> 2026-09-11
        $greg3 = EthiopianDateService::toGregorian(2019, 1, 1);
        $this->assertSame(2026, $greg3['year']);
        $this->assertSame(9, $greg3['month']);
        $this->assertSame(11, $greg3['day']);
        $this->assertSame('2026-09-11', $greg3['formatted']);
    }

    public function test_bidirectional_roundtrip_is_exact(): void
    {
        // Test 1,500 consecutive dates from 2023 to 2027
        $current = new \DateTimeImmutable('2023-01-01');
        for ($i = 0; $i < 1500; $i++) {
            $gy = (int) $current->format('Y');
            $gm = (int) $current->format('n');
            $gd = (int) $current->format('j');

            $eth = EthiopianDateService::fromGregorian($gy, $gm, $gd);
            $back = EthiopianDateService::toGregorian($eth['year'], $eth['month'], $eth['day']);

            $this->assertSame($gy, $back['year'], "Year mismatch at iteration {$i}");
            $this->assertSame($gm, $back['month'], "Month mismatch at iteration {$i}");
            $this->assertSame($gd, $back['day'], "Day mismatch at iteration {$i}");

            $current = $current->modify('+1 day');
        }
    }

    public function test_pagume_leap_year_handling(): void
    {
        // 2015 E.C. is a leap year (2015 % 4 = 3) -> Pagume has 6 days
        // Pagume 6, 2015 E.C. is September 11, 2023
        $p6 = EthiopianDateService::toGregorian(2015, 13, 6);
        $this->assertSame('2023-09-11', $p6['formatted']);

        // 2016 E.C. is not a leap year (2016 % 4 = 0) -> Pagume 6 must throw InvalidArgumentException
        $this->expectException(InvalidArgumentException::class);
        EthiopianDateService::toGregorian(2016, 13, 6);
    }

    public function test_parses_and_converts_ethiopian_date_strings(): void
    {
        $this->assertSame('2026-09-28', EthiopianDateService::parseAndConvertToGregorian('18/01/2019 ዓ.ም'));
        $this->assertSame('2026-09-28', EthiopianDateService::parseAndConvertToGregorian('18/01/2019 9.9'));
        $this->assertSame('2026-08-26', EthiopianDateService::parseAndConvertToGregorian('20/12/2018'));
        $this->assertNull(EthiopianDateService::parseAndConvertToGregorian(null));
        $this->assertNull(EthiopianDateService::parseAndConvertToGregorian('invalid-date'));
    }

    public function test_formats_date_string(): void
    {
        $formatted = EthiopianDateService::formatFromDateString('2026-09-28');
        $this->assertSame('18/01/2019 ዓ.ም', $formatted);
    }
}
