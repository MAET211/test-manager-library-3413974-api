<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\Exceptions\ValidationException;
use App\Domain\ValueObjects\UserRole;

final class User
{
    private string $id;
    private string $username;
    private string $passwordHash;
    private UserRole $role;

    public function __construct(
        string $id,
        string $username,
        string $passwordHash,
        UserRole $role
    ) {
        $normalizedUsername = strtolower(trim($username));

        if ($normalizedUsername === '') {
            throw new ValidationException(
                message: 'El nombre de usuario no puede estar vacío.',
                details: [['field' => 'username', 'issue' => 'no puede estar vacío']]
            );
        }

        if (mb_strlen($normalizedUsername) > 120) {
            throw new ValidationException(
                message: 'El nombre de usuario no puede superar los 120 caracteres.',
                details: [['field' => 'username', 'issue' => 'longitud máxima 120']]
            );
        }

        if (trim($passwordHash) === '') {
            throw new ValidationException(
                message: 'El hash de la contraseña no puede estar vacío.',
                details: [['field' => 'password', 'issue' => 'hash requerido']]
            );
        }

        $this->id = $id;
        $this->username = $normalizedUsername;
        $this->passwordHash = $passwordHash;
        $this->role = $role;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function username(): string
    {
        return $this->username;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function role(): UserRole
    {
        return $this->role;
    }

    public function isAdmin(): bool
    {
        return $this->role->isAdmin();
    }

    public function isLibrarian(): bool
    {
        return $this->role->isLibrarian();
    }
}
