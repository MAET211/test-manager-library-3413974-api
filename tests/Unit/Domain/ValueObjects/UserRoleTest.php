<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\Exceptions\ValidationException;
use App\Domain\ValueObjects\UserRole;
use PHPUnit\Framework\TestCase;

final class UserRoleTest extends TestCase
{
    public function test_parses_admin_role(): void
    {
        $role = UserRole::fromString('admin');

        $this->assertSame(UserRole::ADMIN, $role);
        $this->assertTrue($role->isAdmin());
        $this->assertFalse($role->isLibrarian());
    }

    public function test_parses_librarian_role(): void
    {
        $role = UserRole::fromString('librarian');

        $this->assertSame(UserRole::LIBRARIAN, $role);
        $this->assertTrue($role->isLibrarian());
        $this->assertFalse($role->isAdmin());
    }

    public function test_normalizes_case_and_whitespace(): void
    {
        $role = UserRole::fromString('  Librarian  ');

        $this->assertSame(UserRole::LIBRARIAN, $role);
    }

    public function test_invalid_role_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Rol inválido: «superadmin».');

        UserRole::fromString('superadmin');
    }
}
