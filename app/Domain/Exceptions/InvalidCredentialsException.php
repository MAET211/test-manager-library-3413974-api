<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

final class InvalidCredentialsException extends DomainException
{
    /**
     * @param array<int, array{field: string, issue: string}> $details
     */
    public function __construct(string $message = 'Credenciales de acceso inválidas.', array $details = [])
    {
        parent::__construct(
            errorCode: 'INVALID_CREDENTIALS',
            message: $message,
            httpStatusCode: 401,
            details: $details
        );
    }
}
