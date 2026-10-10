<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

final class DuplicateBookInLoanException extends DomainException
{
    /**
     * @param array<int, array{field: string, issue: string}> $details
     */
    public function __construct(string $message = 'No se puede incluir el mismo libro más de una vez en un préstamo.', array $details = [])
    {
        parent::__construct(
            errorCode: 'DUPLICATE_BOOK_IN_LOAN',
            message: $message,
            httpStatusCode: 422,
            details: $details
        );
    }
}
