<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\Ports\Outbound\PasswordHasherInterface;

final class BcryptPasswordHasher implements PasswordHasherInterface
{
    private int $cost;

    public function __construct(int $cost = 12)
    {
        $this->cost = $cost;
    }

    public function hash(string $plainPassword): string
    {
        return password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => $this->cost]);
    }

    public function verify(string $plainPassword, string $hash): bool
    {
        return password_verify($plainPassword, $hash);
    }
}
