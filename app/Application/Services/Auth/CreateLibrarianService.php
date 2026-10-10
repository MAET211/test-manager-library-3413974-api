<?php

declare(strict_types=1);

namespace App\Application\Services\Auth;

use App\Application\Ports\Outbound\PasswordHasherInterface;
use App\Application\Ports\Outbound\UserRepositoryInterface;
use App\Domain\Entities\User;
use App\Domain\Exceptions\UsernameTakenException;
use App\Domain\Exceptions\ValidationException;
use App\Domain\ValueObjects\UserRole;

final readonly class CreateLibrarianService
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher
    ) {
    }

    /**
     * @return array{id: string, username: string, role: string}
     */
    public function execute(string $id, string $username, string $password, string $role): array
    {
        $normalizedRole = strtolower(trim($role));

        // CA-02.3: Pedir el rol admin por la API responde 422
        if ($normalizedRole === 'admin') {
            throw new ValidationException(
                message: 'No está permitido crear usuarios con rol «admin» a través de la API.',
                details: [['field' => 'role', 'issue' => 'solo se permite crear el rol librarian']]
            );
        }

        if ($normalizedRole !== 'librarian') {
            throw new ValidationException(
                message: "Rol inválido: «{$role}». Solo se permite «librarian».",
                details: [['field' => 'role', 'issue' => 'debe ser librarian']]
            );
        }

        $normalizedUsername = strtolower(trim($username));

        // CA-02.2: Nombre de usuario único (409)
        if ($this->userRepository->findByUsername($normalizedUsername) !== null) {
            throw new UsernameTakenException(
                username: $normalizedUsername,
                details: [['field' => 'username', 'issue' => 'nombre de usuario ya en uso']]
            );
        }

        if (trim($password) === '') {
            throw new ValidationException(
                message: 'La contraseña no puede estar vacía.',
                details: [['field' => 'password', 'issue' => 'contraseña requerida']]
            );
        }

        $passwordHash = $this->passwordHasher->hash($password);
        $user = new User(
            id: $id,
            username: $normalizedUsername,
            passwordHash: $passwordHash,
            role: UserRole::LIBRARIAN
        );

        $this->userRepository->save($user);

        // CA-02.3: La clave nunca se devuelve
        return [
            'id' => $user->id(),
            'username' => $user->username(),
            'role' => $user->role()->value,
        ];
    }
}
