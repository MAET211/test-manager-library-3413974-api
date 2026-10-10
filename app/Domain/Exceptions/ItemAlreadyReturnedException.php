<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

final class ItemAlreadyReturnedException extends DomainException
{
    /**
     * @param array<int, array{field: string, issue: string}> $details
     */
    public function __construct(string $message = 'La línea del préstamo ya ha sido devuelta previamente.', array $details = [])
    {
        parent::__construct(
            errorCode: 'ITEM_ALREADY_RETURNED',
            message: $message,
            httpStatusCode: 409,
            details: $details
        );
    }
}
