<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

final class ForbiddenRoleException extends DomainException
{
    /**
     * @param array<int, array{field: string, issue: string}> $details
     */
    public function __construct(string $message = 'No posee permisos para ejecutar esta acción.', array $details = [])
    {
        parent::__construct(
            errorCode: 'FORBIDDEN_ROLE',
            message: $message,
            httpStatusCode: 403,
            details: $details
        );
    }
}
