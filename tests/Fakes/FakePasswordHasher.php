<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Application\Ports\Outbound\PasswordHasherInterface;

final class FakePasswordHasher implements PasswordHasherInterface
{
    public function hash(string $plainPassword): string
    {
        return 'fake_hash_' . $plainPassword;
    }

    public function verify(string $plainPassword, string $hash): bool
    {
        return $hash === ('fake_hash_' . $plainPassword);
    }
}
