<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Auth;

use App\Application\Services\Auth\CreateLibrarianService;
use App\Application\Services\Auth\LoginService;
use App\Domain\Entities\User;
use App\Domain\Exceptions\InvalidCredentialsException;
use App\Domain\Exceptions\UsernameTakenException;
use App\Domain\Exceptions\ValidationException;
use App\Domain\ValueObjects\UserRole;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakePasswordHasher;
use Tests\Fakes\FakeTokenIssuer;
use Tests\Fakes\InMemoryUserRepository;

final class AuthServicesTest extends TestCase
{
    private InMemoryUserRepository $userRepository;
    private FakePasswordHasher $passwordHasher;
    private FakeTokenIssuer $tokenIssuer;
    private LoginService $loginService;
    private CreateLibrarianService $createLibrarianService;

    protected function setUp(): void
    {
        $this->userRepository = new InMemoryUserRepository();
        $this->passwordHasher = new FakePasswordHasher();
        $this->tokenIssuer = new FakeTokenIssuer();

        $this->loginService = new LoginService(
            $this->userRepository,
            $this->passwordHasher,
            $this->tokenIssuer
        );

        $this->createLibrarianService = new CreateLibrarianService(
            $this->userRepository,
            $this->passwordHasher
        );
    }

    public function test_login_succeeds_with_valid_credentials(): void
    {
        $user = new User(
            id: 'u-1',
            username: 'ana',
            passwordHash: $this->passwordHasher->hash('secreto123'),
            role: UserRole::LIBRARIAN
        );
        $this->userRepository->save($user);

        $result = $this->loginService->execute('ana', 'secreto123');

        $this->assertArrayHasKey('access_token', $result);
        $this->assertSame('Bearer', $result['token_type']);
        $this->assertSame(28800, $result['expires_in']);
        $this->assertSame('librarian', $result['role']);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        $user = new User(
            id: 'u-1',
            username: 'ana',
            passwordHash: $this->passwordHasher->hash('secreto123'),
            role: UserRole::LIBRARIAN
        );
        $this->userRepository->save($user);

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Usuario o contraseña incorrectos.');

        $this->loginService->execute('ana', 'clave_incorrecta');
    }

    public function test_login_fails_with_non_existent_username(): void
    {
        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Usuario o contraseña incorrectos.');

        $this->loginService->execute('inexistente', 'clave');
    }

    public function test_create_librarian_succeeds(): void
    {
        $result = $this->createLibrarianService->execute(
            id: 'u-2',
            username: '  Carlos  ',
            password: 'secretPassword1',
            role: 'librarian'
        );

        $this->assertSame('u-2', $result['id']);
        $this->assertSame('carlos', $result['username']);
        $this->assertSame('librarian', $result['role']);

        $savedUser = $this->userRepository->findByUsername('carlos');
        $this->assertNotNull($savedUser);
        $this->assertTrue($this->passwordHasher->verify('secretPassword1', $savedUser->passwordHash()));
    }

    public function test_create_librarian_fails_if_requesting_admin_role(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('No está permitido crear usuarios con rol «admin» a través de la API.');

        $this->createLibrarianService->execute(
            id: 'u-3',
            username: 'admin2',
            password: 'password',
            role: 'admin'
        );
    }

    public function test_create_librarian_fails_if_username_already_taken(): void
    {
        $existing = new User(
            id: 'u-existing',
            username: 'maria',
            passwordHash: $this->passwordHasher->hash('pass'),
            role: UserRole::LIBRARIAN
        );
        $this->userRepository->save($existing);

        $this->expectException(UsernameTakenException::class);
        $this->expectExceptionMessage('El nombre de usuario «maria» ya está en uso.');

        $this->createLibrarianService->execute(
            id: 'u-new',
            username: 'Maria',
            password: 'password',
            role: 'librarian'
        );
    }
}
