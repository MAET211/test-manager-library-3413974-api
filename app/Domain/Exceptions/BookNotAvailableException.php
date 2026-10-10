<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

final class BookNotAvailableException extends DomainException
{
    /**
     * @param array<int, array{field: string, issue: string}> $details
     */
    public function __construct(string $bookTitle = '', array $details = [])
    {
        $message = $bookTitle !== ''
            ? "No quedan ejemplares disponibles de «{$bookTitle}»."
            : 'No quedan ejemplares disponibles del libro solicitado.';

        parent::__construct(
            errorCode: 'BOOK_NOT_AVAILABLE',
            message: $message,
            httpStatusCode: 409,
            details: $details
        );
    }
}
