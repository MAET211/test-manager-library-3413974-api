<?php

declare(strict_types=1);

namespace App\Infrastructure\Clock;

use App\Application\Ports\Outbound\ClockInterface;
use DateTimeImmutable;
use DateTimeZone;

final class SystemClock implements ClockInterface
{
    private DateTimeZone $utc;

    public function __construct()
    {
        $this->utc = new DateTimeZone('UTC');
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->utc);
    }
}
