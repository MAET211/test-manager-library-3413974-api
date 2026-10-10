<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

final class IsbnTakenException extends DomainException
{
    /**
     * @param array<int, array{field: string, issue: string}> $details
     */
    public function __construct(string $isbn = '', array $details = [])
    {
        $message = $isbn !== ''
            ? "El ISBN «{$isbn}» ya está registrado en otro libro activo."
            : 'El ISBN ya está registrado en un libro activo.';

        parent::__construct(
            errorCode: 'ISBN_TAKEN',
            message: $message,
            httpStatusCode: 409,
            details: $details
        );
    }
}
