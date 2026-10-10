<?php

declare(strict_types=1);

namespace App\Application\Services\Auth;

use App\Application\Ports\Outbound\PasswordHasherInterface;
use App\Application\Ports\Outbound\TokenIssuerInterface;
use App\Application\Ports\Outbound\UserRepositoryInterface;
use App\Domain\Exceptions\InvalidCredentialsException;

final readonly class LoginService
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher,
        private TokenIssuerInterface $tokenIssuer
    ) {
    }

    /**
     * @return array{access_token: string, token_type: string, expires_in: int, role: string}
     */
    public function execute(string $username, string $password): array
    {
        $normalizedUsername = strtolower(trim($username));
        $user = $this->userRepository->findByUsername($normalizedUsername);

        if ($user === null || !$this->passwordHasher->verify($password, $user->passwordHash())) {
            // CA-01.2: No dice si falló el usuario o la clave
            throw new InvalidCredentialsException('Usuario o contraseña incorrectos.');
        }

        $ttl = 28800; // 8 horas en segundos
        $token = $this->tokenIssuer->issue($user, $ttl);

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $ttl,
            'role' => $user->role()->value,
        ];
    }
}
