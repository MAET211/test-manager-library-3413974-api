<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

final class ValidationException extends DomainException
{
    /**
     * @param array<int, array{field: string, issue: string}> $details
     */
    public function __construct(string $message, array $details = [])
    {
        parent::__construct(
            errorCode: 'VALIDATION_ERROR',
            message: $message,
            httpStatusCode: 422,
            details: $details
        );
    }
}
