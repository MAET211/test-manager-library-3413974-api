<?php

declare(strict_types=1);

namespace Tests;

use App\Application\Ports\Outbound\TokenIssuerInterface;
use App\Application\Ports\Outbound\UserRepositoryInterface;
use App\Domain\Entities\User;
use App\Domain\ValueObjects\UserRole;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function cleanDatabase(): void
    {
        DB::statement('TRUNCATE library.loan_item, library.loan, library.book, library.member CASCADE');
        DB::statement("DELETE FROM library.\"user\" WHERE username != 'admin'");
    }

    protected function getAdminToken(): string
    {
        $tokenIssuer = $this->app->make(TokenIssuerInterface::class);
        $userRepo = $this->app->make(UserRepositoryInterface::class);
        $admin = $userRepo->findByUsername('admin');

        if ($admin === null) {
            $admin = new User(
                id: '00000000-0000-0000-0000-000000000001',
                username: 'admin',
                passwordHash: password_hash('admin123456', PASSWORD_BCRYPT),
                role: UserRole::ADMIN
            );
            $userRepo->save($admin);
        }

        return $tokenIssuer->issue($admin, 28800);
    }

    protected function createLibrarian(string $username = 'librarian'): User
    {
        $userRepo = $this->app->make(UserRepositoryInterface::class);
        $existing = $userRepo->findByUsername($username);
        if ($existing !== null) {
            return $existing;
        }

        $librarian = new User(
            id: (string) Str::uuid(),
            username: $username,
            passwordHash: password_hash('secret123', PASSWORD_BCRYPT),
            role: UserRole::LIBRARIAN
        );
        $userRepo->save($librarian);

        return $librarian;
    }

    protected function getLibrarianToken(string $username = 'librarian'): string
    {
        $tokenIssuer = $this->app->make(TokenIssuerInterface::class);
        $librarian = $this->createLibrarian($username);

        return $tokenIssuer->issue($librarian, 28800);
    }
}
