<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

use App\Domain\Exceptions\InvalidDateRangeException;
use DateTimeImmutable;
use DateTimeZone;

final readonly class DateRange
{
    private DateTimeImmutable $from;
    private DateTimeImmutable $to;

    public function __construct(DateTimeImmutable $from, DateTimeImmutable $to)
    {
        if ($to < $from) {
            throw new InvalidDateRangeException(
                message: 'La fecha final no puede ser anterior a la fecha inicial.',
                details: [['field' => 'to', 'issue' => 'debe ser posterior o igual a from']]
            );
        }

        $this->from = $from;
        $this->to = $to;
    }

    public static function fromStrings(string $fromDate, string $toDate): self
    {
        $utc = new DateTimeZone('UTC');

        $from = DateTimeImmutable::createFromFormat('!Y-m-d', trim($fromDate), $utc)
            ?: DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, trim($fromDate), $utc);

        $to = DateTimeImmutable::createFromFormat('!Y-m-d', trim($toDate), $utc)
            ?: DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, trim($toDate), $utc);

        if ($from === false || $to === false) {
            throw new InvalidDateRangeException(
                message: 'Formato de fecha inválido. Se espera YYYY-MM-DD o formato ISO 8601 UTC.',
                details: [['field' => 'dates', 'issue' => 'formato de fecha no reconocido']]
            );
        }

        return new self($from, $to);
    }

    public function from(): DateTimeImmutable
    {
        return $this->from;
    }

    public function to(): DateTimeImmutable
    {
        return $this->to;
    }

    public function fromDateString(): string
    {
        return $this->from->format('Y-m-d');
    }

    public function toDateString(): string
    {
        return $this->to->format('Y-m-d');
    }
}
