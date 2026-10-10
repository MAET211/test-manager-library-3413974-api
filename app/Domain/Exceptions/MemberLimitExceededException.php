<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

final class MemberLimitExceededException extends DomainException
{
    /**
     * @param array<int, array{field: string, issue: string}> $details
     */
    public function __construct(int $currentCount = 0, array $details = [])
    {
        $message = "El socio supera el límite de 3 libros sin devolver (tiene actualmente {$currentCount} libros).";

        parent::__construct(
            errorCode: 'MEMBER_LIMIT_EXCEEDED',
            message: $message,
            httpStatusCode: 422,
            details: $details
        );
    }
}
