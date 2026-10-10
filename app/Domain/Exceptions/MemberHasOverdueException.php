<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

final class MemberHasOverdueException extends DomainException
{
    /**
     * @param array<int, array{field: string, issue: string}> $details
     */
    public function __construct(string $message = 'El socio tiene libros vencidos pendientes de devolución.', array $details = [])
    {
        parent::__construct(
            errorCode: 'MEMBER_HAS_OVERDUE',
            message: $message,
            httpStatusCode: 409,
            details: $details
        );
    }
}
