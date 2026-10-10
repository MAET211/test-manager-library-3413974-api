<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Ports\Outbound\BookRepositoryInterface;
use App\Application\Ports\Outbound\CategoryRepositoryInterface;
use App\Application\Ports\Outbound\ClockInterface;
use App\Application\Ports\Outbound\CoverStorageInterface;
use App\Application\Ports\Outbound\LoanRepositoryInterface;
use App\Application\Ports\Outbound\MemberRepositoryInterface;
use App\Application\Ports\Outbound\MostBorrowedReportReaderInterface;
use App\Application\Ports\Outbound\PasswordHasherInterface;
use App\Application\Ports\Outbound\TokenIssuerInterface;
use App\Application\Ports\Outbound\UserRepositoryInterface;
use App\Domain\Entities\User;
use App\Domain\ValueObjects\UserRole;
use App\Infrastructure\Clock\SystemClock;
use App\Infrastructure\Persistence\Reports\SqlMostBorrowedReportReader;
use App\Infrastructure\Persistence\Repositories\EloquentBookRepository;
use App\Infrastructure\Persistence\Repositories\EloquentCategoryRepository;
use App\Infrastructure\Persistence\Repositories\EloquentLoanRepository;
use App\Infrastructure\Persistence\Repositories\EloquentMemberRepository;
use App\Infrastructure\Persistence\Repositories\EloquentUserRepository;
use App\Infrastructure\Security\BcryptPasswordHasher;
use App\Infrastructure\Security\JwtTokenIssuer;
use App\Infrastructure\Storage\DiskCoverStorage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

final class LibraryServiceProvider extends ServiceProvider
{
    /**
     * Artículo III: Un único punto de composición.
     */
    public function register(): void
    {
        // Persistencia
        $this->app->singleton(BookRepositoryInterface::class, EloquentBookRepository::class);
        $this->app->singleton(LoanRepositoryInterface::class, EloquentLoanRepository::class);
        $this->app->singleton(MemberRepositoryInterface::class, EloquentMemberRepository::class);
        $this->app->singleton(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->singleton(CategoryRepositoryInterface::class, EloquentCategoryRepository::class);
        $this->app->singleton(MostBorrowedReportReaderInterface::class, SqlMostBorrowedReportReader::class);

        // Seguridad, almacenamiento y reloj
        $this->app->singleton(ClockInterface::class, SystemClock::class);
        $this->app->singleton(PasswordHasherInterface::class, BcryptPasswordHasher::class);
        $this->app->singleton(TokenIssuerInterface::class, JwtTokenIssuer::class);
        $this->app->singleton(CoverStorageInterface::class, DiskCoverStorage::class);
    }

    public function boot(): void
    {
        // data-model §9 & T-17: Creación del administrador inicial desde variables de entorno
        try {
            $userRepo = $this->app->make(UserRepositoryInterface::class);
            $hasher = $this->app->make(PasswordHasherInterface::class);

            $adminUsername = strtolower(trim((string) env('INITIAL_ADMIN_USERNAME', 'admin')));
            $adminPassword = (string) env('INITIAL_ADMIN_PASSWORD', 'admin123456');

            if ($adminUsername !== '' && $adminPassword !== '') {
                $existing = $userRepo->findByUsername($adminUsername);
                if ($existing === null) {
                    $adminId = '00000000-0000-0000-0000-000000000001';
                    $adminUser = new User(
                        id: $adminId,
                        username: $adminUsername,
                        passwordHash: $hasher->hash($adminPassword),
                        role: UserRole::ADMIN
                    );
                    $userRepo->save($adminUser);
                }
            }
        } catch (\Throwable $e) {
            // Ignorar en comandos de consola como migrate si las tablas aún no existen
        }
    }
}
