<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

final class InvalidDateRangeException extends DomainException
{
    /**
     * @param array<int, array{field: string, issue: string}> $details
     */
    public function __construct(string $message = 'El rango de fechas es inválido: la fecha final no puede ser anterior a la inicial.', array $details = [])
    {
        parent::__construct(
            errorCode: 'INVALID_DATE_RANGE',
            message: $message,
            httpStatusCode: 422,
            details: $details
        );
    }
}
