<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\Ports\Outbound\TokenIssuerInterface;
use App\Domain\Entities\User;
use App\Domain\Exceptions\InvalidCredentialsException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

final readonly class JwtTokenIssuer implements TokenIssuerInterface
{
    private string $secret;

    public function __construct(?string $secret = null)
    {
        $this->secret = $secret ?? (string) env('JWT_SECRET', 'manager_library_jwt_secret_key_adso_3413974_sena');
    }

    public function issue(User $user, int $ttlSeconds = 28800): string
    {
        $now = time();
        $payload = [
            'iss' => 'manager-library-api',
            'sub' => $user->id(),
            'role' => $user->role()->value,
            'iat' => $now,
            'exp' => $now + $ttlSeconds,
        ];

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    public function verify(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));

            return [
                'sub' => (string) $decoded->sub,
                'role' => (string) $decoded->role,
                'exp' => (int) $decoded->exp,
            ];
        } catch (Throwable $e) {
            throw new InvalidCredentialsException(
                message: 'Token de acceso inválido o expirado.',
                details: [['field' => 'authorization', 'issue' => 'token inválido o expirado']]
            );
        }
    }
}
