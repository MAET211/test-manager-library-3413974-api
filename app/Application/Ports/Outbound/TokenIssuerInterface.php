<?php

declare(strict_types=1);

namespace App\Application\Ports\Outbound;

use App\Domain\Entities\User;

interface TokenIssuerInterface
{
    public function issue(User $user, int $ttlSeconds = 28800): string;

    /**
     * @return array{sub: string, role: string, exp: int}
     */
    public function verify(string $token): array;
}
