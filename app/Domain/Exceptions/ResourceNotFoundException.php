<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

final class ResourceNotFoundException extends DomainException
{
    /**
     * @param array<int, array{field: string, issue: string}> $details
     */
    public function __construct(string $message = 'El recurso solicitado no fue encontrado o ha sido dado de baja.', array $details = [])
    {
        parent::__construct(
            errorCode: 'NOT_FOUND',
            message: $message,
            httpStatusCode: 404,
            details: $details
        );
    }
}
