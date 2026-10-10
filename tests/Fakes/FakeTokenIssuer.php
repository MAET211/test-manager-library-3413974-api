<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Application\Ports\Outbound\TokenIssuerInterface;
use App\Domain\Entities\User;

final class FakeTokenIssuer implements TokenIssuerInterface
{
    public function issue(User $user, int $ttlSeconds = 28800): string
    {
        return 'fake_token_for_' . $user->username() . '_' . $user->role()->value;
    }

    public function verify(string $token): array
    {
        // fake_token_for_{username}_{role}
        $parts = explode('_', $token);
        $role = end($parts) ?: 'librarian';

        return [
            'sub' => 'fake-sub-id',
            'role' => $role,
            'exp' => time() + 28800,
        ];
    }
}
