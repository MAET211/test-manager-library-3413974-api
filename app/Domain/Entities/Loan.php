<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\Exceptions\DuplicateBookInLoanException;
use App\Domain\Exceptions\ValidationException;
use App\Domain\ValueObjects\LoanStatus;
use DateTimeImmutable;

final class Loan
{
    private string $id;
    private string $memberId;
    private string $loanedByUserId;
    private DateTimeImmutable $loanedAt;

    /**
     * @var array<string, LoanItem> Indexado por id de ítem
     */
    private array $items = [];

    /**
     * @param array<int, LoanItem> $items
     */
    public function __construct(
        string $id,
        string $memberId,
        string $loanedByUserId,
        DateTimeImmutable $loanedAt,
        array $items
    ) {
        $count = count($items);
        if ($count < 1 || $count > 3) {
            throw new ValidationException(
                message: "Un préstamo debe contener entre 1 y 3 libros (se recibieron {$count}).",
                details: [['field' => 'items', 'issue' => 'debe contener entre 1 y 3 libros']]
            );
        }

        // Validar libros duplicados (CA-06.2)
        $seenBookIds = [];
        foreach ($items as $item) {
            $bookId = $item->bookId();
            if (isset($seenBookIds[$bookId])) {
                throw new DuplicateBookInLoanException(
                    message: "No se puede incluir el mismo libro más de una vez en un préstamo (ID: {$bookId}).",
                    details: [['field' => 'items', 'issue' => 'libro repetido en el préstamo']]
                );
            }
            $seenBookIds[$bookId] = true;
            $this->items[$item->id()] = $item;
        }

        $this->id = $id;
        $this->memberId = $memberId;
        $this->loanedByUserId = $loanedByUserId;
        $this->loanedAt = $loanedAt;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function memberId(): string
    {
        return $this->memberId;
    }

    public function loanedByUserId(): string
    {
        return $this->loanedByUserId;
    }

    public function loanedAt(): DateTimeImmutable
    {
        return $this->loanedAt;
    }

    /**
     * @return array<int, LoanItem>
     */
    public function items(): array
    {
        return array_values($this->items);
    }

    public function findItem(string $itemId): ?LoanItem
    {
        return $this->items[$itemId] ?? null;
    }

    /**
     * Devuelve una línea específica de este préstamo (CA-07.1).
     */
    public function returnItem(string $itemId, DateTimeImmutable $returnedAt): LoanItem
    {
        $item = $this->findItem($itemId);

        if ($item === null) {
            throw new ValidationException(
                message: "Línea de préstamo «{$itemId}» no encontrada en el préstamo «{$this->id}».",
                details: [['field' => 'item_id', 'issue' => 'ítem no pertenece a este préstamo']]
            );
        }

        $item->returnItem($returnedAt);

        return $item;
    }

    /**
     * Calcula el estado derivado del préstamo en tiempo de ejecución (CA-08.3 y Artículo VII).
     * open: hay líneas sin devolver y ninguna vencida.
     * overdue: alguna línea sin devolver está vencida.
     * closed: todas las líneas están devueltas.
     */
    public function status(DateTimeImmutable $now): LoanStatus
    {
        $allReturned = true;
        $hasOverdue = false;

        foreach ($this->items as $item) {
            if (!$item->isReturned()) {
                $allReturned = false;
                if ($item->isOverdue($now)) {
                    $hasOverdue = true;
                }
            }
        }

        if ($allReturned) {
            return LoanStatus::CLOSED;
        }

        if ($hasOverdue) {
            return LoanStatus::OVERDUE;
        }

        return LoanStatus::OPEN;
    }

    /**
     * Conteo de líneas aún sin devolver.
     */
    public function activeItemsCount(): int
    {
        $count = 0;
        foreach ($this->items as $item) {
            if (!$item->isReturned()) {
                $count++;
            }
        }

        return $count;
    }
}
