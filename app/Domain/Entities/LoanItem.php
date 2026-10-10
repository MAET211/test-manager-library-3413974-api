<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\Exceptions\ItemAlreadyReturnedException;
use App\Domain\Exceptions\ValidationException;
use DateTimeImmutable;

final class LoanItem
{
    private string $id;
    private string $loanId;
    private string $bookId;
    private string $bookTitle;
    private string $isbn;
    private string $categoryName;
    private DateTimeImmutable $dueAt;
    private ?DateTimeImmutable $returnedAt;

    public function __construct(
        string $id,
        string $loanId,
        string $bookId,
        string $bookTitle,
        string $isbn,
        string $categoryName,
        DateTimeImmutable $dueAt,
        ?DateTimeImmutable $returnedAt = null
    ) {
        $trimmedTitle = trim($bookTitle);
        $trimmedCategory = trim($categoryName);

        if ($trimmedTitle === '') {
            throw new ValidationException(
                message: 'El título del libro congelado no puede estar vacío.',
                details: [['field' => 'book_title', 'issue' => 'no puede estar vacío']]
            );
        }

        if ($trimmedCategory === '') {
            throw new ValidationException(
                message: 'El nombre de categoría congelado no puede estar vacío.',
                details: [['field' => 'category_name', 'issue' => 'no puede estar vacío']]
            );
        }

        $this->id = $id;
        $this->loanId = $loanId;
        $this->bookId = $bookId;
        $this->bookTitle = $trimmedTitle;
        $this->isbn = $isbn;
        $this->categoryName = $trimmedCategory;
        $this->dueAt = $dueAt;
        $this->returnedAt = $returnedAt;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function loanId(): string
    {
        return $this->loanId;
    }

    public function bookId(): string
    {
        return $this->bookId;
    }

    public function bookTitle(): string
    {
        return $this->bookTitle;
    }

    public function isbn(): string
    {
        return $this->isbn;
    }

    public function categoryName(): string
    {
        return $this->categoryName;
    }

    public function dueAt(): DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function returnedAt(): ?DateTimeImmutable
    {
        return $this->returnedAt;
    }

    public function isReturned(): bool
    {
        return $this->returnedAt !== null;
    }

    public function isOverdue(DateTimeImmutable $now): bool
    {
        return !$this->isReturned() && $this->dueAt < $now;
    }

    public function daysLate(DateTimeImmutable $now): int
    {
        if (!$this->isOverdue($now)) {
            return 0;
        }

        $diff = $now->diff($this->dueAt);

        return (int) $diff->days;
    }

    /**
     * Registra la devolución de este ítem (CA-07.1 / CA-07.2).
     */
    public function returnItem(DateTimeImmutable $returnedAt): void
    {
        if ($this->isReturned()) {
            throw new ItemAlreadyReturnedException(
                message: "La línea del libro «{$this->bookTitle}» ya fue devuelta previamente.",
                details: [['field' => 'item_id', 'issue' => 'línea ya devuelta']]
            );
        }

        $this->returnedAt = $returnedAt;
    }
}
