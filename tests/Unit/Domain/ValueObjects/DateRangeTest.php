<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\Exceptions\InvalidDateRangeException;
use App\Domain\ValueObjects\DateRange;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class DateRangeTest extends TestCase
{
    public function test_valid_date_range_is_accepted(): void
    {
        $utc = new DateTimeZone('UTC');
        $from = new DateTimeImmutable('2026-10-01', $utc);
        $to = new DateTimeImmutable('2026-10-31', $utc);

        $range = new DateRange($from, $to);

        $this->assertSame($from, $range->from());
        $this->assertSame($to, $range->to());
        $this->assertSame('2026-10-01', $range->fromDateString());
        $this->assertSame('2026-10-31', $range->toDateString());
    }

    public function test_date_range_with_same_date_is_accepted(): void
    {
        $utc = new DateTimeZone('UTC');
        $date = new DateTimeImmutable('2026-10-15', $utc);

        $range = new DateRange($date, $date);

        $this->assertSame('2026-10-15', $range->fromDateString());
        $this->assertSame('2026-10-15', $range->toDateString());
    }

    public function test_date_range_with_to_before_from_throws_invalid_date_range_exception(): void
    {
        $utc = new DateTimeZone('UTC');
        $from = new DateTimeImmutable('2026-10-31', $utc);
        $to = new DateTimeImmutable('2026-10-01', $utc);

        $this->expectException(InvalidDateRangeException::class);
        $this->expectExceptionMessage('La fecha final no puede ser anterior a la fecha inicial.');

        new DateRange($from, $to);
    }

    public function test_from_strings_factory_parses_iso_dates(): void
    {
        $range = DateRange::fromStrings('2026-01-01', '2026-12-31');

        $this->assertSame('2026-01-01', $range->fromDateString());
        $this->assertSame('2026-12-31', $range->toDateString());
    }
}
