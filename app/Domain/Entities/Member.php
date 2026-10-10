<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\Exceptions\MemberHasOverdueException;
use App\Domain\Exceptions\MemberLimitExceededException;
use App\Domain\Exceptions\ValidationException;

final class Member
{
    private string $id;
    private string $fullName;
    private string $documentNumber;

    public function __construct(
        string $id,
        string $fullName,
        string $documentNumber
    ) {
        $trimmedName = trim($fullName);
        $trimmedDoc = trim($documentNumber);

        if ($trimmedName === '') {
            throw new ValidationException(
                message: 'El nombre del socio no puede estar vacío.',
                details: [['field' => 'full_name', 'issue' => 'no puede estar vacío']]
            );
        }

        if (mb_strlen($trimmedName) > 200) {
            throw new ValidationException(
                message: 'El nombre del socio no puede superar los 200 caracteres.',
                details: [['field' => 'full_name', 'issue' => 'longitud máxima 200']]
            );
        }

        if ($trimmedDoc === '') {
            throw new ValidationException(
                message: 'El documento del socio no puede estar vacío.',
                details: [['field' => 'document_number', 'issue' => 'no puede estar vacío']]
            );
        }

        if (mb_strlen($trimmedDoc) > 30) {
            throw new ValidationException(
                message: 'El documento no puede superar los 30 caracteres.',
                details: [['field' => 'document_number', 'issue' => 'longitud máxima 30']]
            );
        }

        $this->id = $id;
        $this->fullName = $trimmedName;
        $this->documentNumber = $trimmedDoc;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function fullName(): string
    {
        return $this->fullName;
    }

    public function documentNumber(): string
    {
        return $this->documentNumber;
    }

    /**
     * Valida si el socio está habilitado para tomar nuevos libros prestados (CA-06.4 y CA-06.5).
     */
    public function canBorrow(int $currentActiveLoansCount, int $newBooksCount, bool $hasOverdue): void
    {
        if ($hasOverdue) {
            throw new MemberHasOverdueException(
                message: "El socio «{$this->fullName}» tiene libros vencidos pendientes de devolución.",
                details: [['field' => 'member_id', 'issue' => 'socio con libros vencidos']]
            );
        }

        if ($currentActiveLoansCount + $newBooksCount > 3) {
            throw new MemberLimitExceededException(
                currentCount: $currentActiveLoansCount,
                details: [['field' => 'member_id', 'issue' => 'supera el límite de 3 libros activos']]
            );
        }
    }
}
