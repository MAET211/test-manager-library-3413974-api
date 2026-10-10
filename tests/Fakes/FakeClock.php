<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Application\Ports\Outbound\ClockInterface;
use DateTimeImmutable;
use DateTimeZone;

final class FakeClock implements ClockInterface
{
    private DateTimeImmutable $now;

    public function __construct(?DateTimeImmutable $initial = null)
    {
        $this->now = $initial ?? new DateTimeImmutable('2026-10-14 10:00:00', new DateTimeZone('UTC'));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function set(DateTimeImmutable $time): void
    {
        $this->now = $time;
    }

    public function advance(string $modifier): void
    {
        $this->now = $this->now->modify($modifier);
    }
}
