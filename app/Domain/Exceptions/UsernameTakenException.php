<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

final class UsernameTakenException extends DomainException
{
    /**
     * @param array<int, array{field: string, issue: string}> $details
     */
    public function __construct(string $username = '', array $details = [])
    {
        $message = $username !== ''
            ? "El nombre de usuario «{$username}» ya está en uso."
            : 'El nombre de usuario ya está registrado.';

        parent::__construct(
            errorCode: 'USERNAME_TAKEN',
            message: $message,
            httpStatusCode: 409,
            details: $details
        );
    }
}
