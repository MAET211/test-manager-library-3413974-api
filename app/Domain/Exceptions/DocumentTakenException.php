<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

final class DocumentTakenException extends DomainException
{
    /**
     * @param array<int, array{field: string, issue: string}> $details
     */
    public function __construct(string $documentNumber = '', array $details = [])
    {
        $message = $documentNumber !== ''
            ? "El documento de identidad «{$documentNumber}» ya se encuentra registrado."
            : 'El número de documento ya está registrado.';

        parent::__construct(
            errorCode: 'DOCUMENT_TAKEN',
            message: $message,
            httpStatusCode: 409,
            details: $details
        );
    }
}
