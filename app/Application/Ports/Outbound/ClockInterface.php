<?php

declare(strict_types=1);

namespace App\Application\Ports\Outbound;

use DateTimeImmutable;

interface ClockInterface
{
    public function now(): DateTimeImmutable;
}
