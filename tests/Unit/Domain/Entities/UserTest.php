<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entities;

use App\Domain\Entities\User;
use App\Domain\Exceptions\ValidationException;
use App\Domain\ValueObjects\UserRole;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function test_user_creation_normalizes_username_to_lowercase_and_trimmed(): void
    {
        $user = new User(
            id: 'user-1',
            username: '  Ana Gómez ',
            passwordHash: '$2y$12$hashedpassword...',
            role: UserRole::LIBRARIAN
        );

        $this->assertSame('ana gómez', $user->username());
        $this->assertSame(UserRole::LIBRARIAN, $user->role());
        $this->assertTrue($user->isLibrarian());
        $this->assertFalse($user->isAdmin());
    }

    public function test_admin_user_role_check(): void
    {
        $admin = new User(
            id: 'admin-1',
            username: 'admin',
            passwordHash: '$2y$12$hashedpassword...',
            role: UserRole::ADMIN
        );

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isLibrarian());
    }

    public function test_empty_username_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('El nombre de usuario no puede estar vacío.');

        new User('user-1', '   ', 'hash', UserRole::LIBRARIAN);
    }

    public function test_empty_password_hash_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('El hash de la contraseña no puede estar vacío.');

        new User('user-1', 'ana', '   ', UserRole::LIBRARIAN);
    }
}
