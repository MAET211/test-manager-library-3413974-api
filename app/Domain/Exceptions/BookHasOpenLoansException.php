<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

final class BookHasOpenLoansException extends DomainException
{
    /**
     * @param array<int, array{field: string, issue: string}> $details
     */
    public function __construct(string $message = 'No se puede dar de baja un libro con préstamos abiertos.', array $details = [])
    {
        parent::__construct(
            errorCode: 'BOOK_HAS_OPEN_LOANS',
            message: $message,
            httpStatusCode: 409,
            details: $details
        );
    }
}
