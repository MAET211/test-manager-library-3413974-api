<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\Exceptions\BookHasOpenLoansException;
use App\Domain\Exceptions\BookNotAvailableException;
use App\Domain\Exceptions\ValidationException;
use App\Domain\ValueObjects\Isbn;
use DateTimeImmutable;
use DateTimeZone;

final class Book
{
    private string $id;
    private string $title;
    private string $author;
    private Isbn $isbn;
    private string $categoryId;
    private int $totalCopies;
    private int $availableCopies;
    private ?string $coverKey;
    private ?DateTimeImmutable $deletedAt;

    public function __construct(
        string $id,
        string $title,
        string $author,
        Isbn $isbn,
        string $categoryId,
        int $totalCopies,
        ?int $availableCopies = null,
        ?string $coverKey = null,
        ?DateTimeImmutable $deletedAt = null
    ) {
        $trimmedTitle = trim($title);
        $trimmedAuthor = trim($author);

        if ($trimmedTitle === '') {
            throw new ValidationException(
                message: 'El título del libro no puede estar vacío.',
                details: [['field' => 'title', 'issue' => 'no puede estar vacío']]
            );
        }

        if (mb_strlen($trimmedTitle) > 250) {
            throw new ValidationException(
                message: 'El título no puede exceder los 250 caracteres.',
                details: [['field' => 'title', 'issue' => 'longitud máxima 250']]
            );
        }

        if ($trimmedAuthor === '') {
            throw new ValidationException(
                message: 'El autor del libro no puede estar vacío.',
                details: [['field' => 'author', 'issue' => 'no puede estar vacío']]
            );
        }

        if (mb_strlen($trimmedAuthor) > 200) {
            throw new ValidationException(
                message: 'El autor no puede exceder los 200 caracteres.',
                details: [['field' => 'author', 'issue' => 'longitud máxima 200']]
            );
        }

        if ($totalCopies < 1) {
            throw new ValidationException(
                message: 'El total de ejemplares debe ser mayor o igual a 1.',
                details: [['field' => 'total_copies', 'issue' => 'debe ser mayor o igual a 1']]
            );
        }

        $initialAvailable = $availableCopies ?? $totalCopies;

        if ($initialAvailable < 0 || $initialAvailable > $totalCopies) {
            throw new ValidationException(
                message: "Los ejemplares disponibles ({$initialAvailable}) deben estar entre 0 y el total ({$totalCopies}).",
                details: [['field' => 'available_copies', 'issue' => 'rango inválido']]
            );
        }

        if ($coverKey === '') {
            throw new ValidationException(
                message: 'La clave de portada no puede ser una cadena vacía. Debe ser nula o una clave opaca.',
                details: [['field' => 'cover_key', 'issue' => 'no puede ser cadena vacía']]
            );
        }

        $this->id = $id;
        $this->title = $trimmedTitle;
        $this->author = $trimmedAuthor;
        $this->isbn = $isbn;
        $this->categoryId = $categoryId;
        $this->totalCopies = $totalCopies;
        $this->availableCopies = $initialAvailable;
        $this->coverKey = $coverKey;
        $this->deletedAt = $deletedAt;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function author(): string
    {
        return $this->author;
    }

    public function isbn(): Isbn
    {
        return $this->isbn;
    }

    public function categoryId(): string
    {
        return $this->categoryId;
    }

    public function totalCopies(): int
    {
        return $this->totalCopies;
    }

    public function availableCopies(): int
    {
        return $this->availableCopies;
    }

    public function coverKey(): ?string
    {
        return $this->coverKey;
    }

    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * Descuenta 1 ejemplar disponible al prestar.
     */
    public function withdraw(): void
    {
        if ($this->isDeleted()) {
            throw new BookNotAvailableException(
                $this->title,
                [['field' => 'book_id', 'issue' => 'libro dado de baja']]
            );
        }

        if ($this->availableCopies <= 0) {
            throw new BookNotAvailableException(
                $this->title,
                [['field' => 'available_copies', 'issue' => 'sin ejemplares disponibles']]
            );
        }

        $this->availableCopies--;
    }

    /**
     * Suma 1 ejemplar disponible al devolver.
     */
    public function restock(): void
    {
        if ($this->availableCopies >= $this->totalCopies) {
            throw new ValidationException(
                message: "No se puede devolver el ejemplar: los ejemplares disponibles ({$this->availableCopies}) ya alcanzaron el total ({$this->totalCopies}).",
                details: [['field' => 'available_copies', 'issue' => 'superaría el total']]
            );
        }

        $this->availableCopies++;
    }

    /**
     * Actualiza atributos y ajusta existencias (CA-03.3).
     */
    public function updateDetails(string $title, string $author, string $categoryId, int $newTotalCopies): void
    {
        $trimmedTitle = trim($title);
        $trimmedAuthor = trim($author);

        if ($trimmedTitle === '') {
            throw new ValidationException(
                message: 'El título del libro no puede estar vacío.',
                details: [['field' => 'title', 'issue' => 'no puede estar vacío']]
            );
        }

        if ($trimmedAuthor === '') {
            throw new ValidationException(
                message: 'El autor del libro no puede estar vacío.',
                details: [['field' => 'author', 'issue' => 'no puede estar vacío']]
            );
        }

        if ($newTotalCopies < 1) {
            throw new ValidationException(
                message: 'El total de ejemplares debe ser mayor o igual a 1.',
                details: [['field' => 'total_copies', 'issue' => 'debe ser mayor o igual a 1']]
            );
        }

        $diff = $newTotalCopies - $this->totalCopies;
        $newAvailable = $this->availableCopies + $diff;

        if ($newAvailable < 0) {
            throw new ValidationException(
                message: "El ajuste solicitado dejaría los ejemplares disponibles en negativo ({$newAvailable}). Hay ejemplares prestados.",
                details: [['field' => 'total_copies', 'issue' => 'no se puede reducir por debajo de los ejemplares prestados']]
            );
        }

        $this->title = $trimmedTitle;
        $this->author = $trimmedAuthor;
        $this->categoryId = $categoryId;
        $this->totalCopies = $newTotalCopies;
        $this->availableCopies = $newAvailable;
    }

    /**
     * Baja lógica (CA-03.5 / ADR-003).
     */
    public function softDelete(bool $hasOpenLoans): void
    {
        if ($hasOpenLoans) {
            throw new BookHasOpenLoansException(
                message: "No se puede dar de baja el libro «{$this->title}» porque tiene préstamos abiertos.",
                details: [['field' => 'book_id', 'issue' => 'tiene préstamos activos']]
            );
        }

        $this->deletedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function setCover(?string $coverKey): void
    {
        if ($coverKey === '') {
            throw new ValidationException(
                message: 'La clave de portada no puede ser una cadena vacía.',
                details: [['field' => 'cover_key', 'issue' => 'debe ser null o una clave válida']]
            );
        }

        $this->coverKey = $coverKey;
    }

    public function removeCover(): void
    {
        $this->coverKey = null;
    }
}
