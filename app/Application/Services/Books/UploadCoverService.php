<?php

declare(strict_types=1);

namespace App\Application\Services\Books;

use App\Application\Ports\Outbound\BookRepositoryInterface;
use App\Application\Ports\Outbound\CoverStorageInterface;
use App\Domain\Exceptions\ResourceNotFoundException;
use App\Domain\Exceptions\ValidationException;

final readonly class UploadCoverService
{
    public function __construct(
        private BookRepositoryInterface $bookRepository,
        private CoverStorageInterface $coverStorage
    ) {
    }

    public function execute(string $bookId, string $binaryContent, string $mimeType): string
    {
        $book = $this->bookRepository->findById($bookId);

        if ($book === null || $book->isDeleted()) {
            throw new ResourceNotFoundException(
                message: "El libro con ID «{$bookId}» no existe o ha sido dado de baja.",
                details: [['field' => 'id', 'issue' => 'libro no encontrado']]
            );
        }

        // CA-03.4 & api-contract §4: Solo JPG/PNG
        $allowedMimes = ['image/jpeg', 'image/png'];
        if (!in_array($mimeType, $allowedMimes, true)) {
            throw new ValidationException(
                message: 'Formato de imagen inválido. Solo se admiten imágenes JPG o PNG.',
                details: [['field' => 'file', 'issue' => 'tipo MIME no permitido, debe ser image/jpeg o image/png']]
            );
        }

        // Máx. 2 MB (2 * 1024 * 1024 bytes)
        $maxBytes = 2 * 1024 * 1024;
        if (strlen($binaryContent) > $maxBytes) {
            throw new ValidationException(
                message: 'El archivo de portada supera el límite máximo permitido de 2 MB.',
                details: [['field' => 'file', 'issue' => 'tamaño excede los 2 MB']]
            );
        }

        $coverKey = $this->coverStorage->store($bookId, $binaryContent, $mimeType);
        $book->setCover($coverKey);
        $this->bookRepository->save($book);

        return $coverKey;
    }
}
