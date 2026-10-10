<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Ports\Outbound\CoverStorageInterface;
use App\Application\Services\Books\DeleteCoverService;
use App\Application\Services\Books\GetBookService;
use App\Application\Services\Books\UploadCoverService;
use App\Domain\Exceptions\ValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class CoverController
{
    public function __construct(
        private readonly GetBookService $getBookService,
        private readonly UploadCoverService $uploadCoverService,
        private readonly DeleteCoverService $deleteCoverService,
        private readonly CoverStorageInterface $coverStorage
    ) {
    }

    public function show(string $id): \Symfony\Component\HttpFoundation\Response
    {
        $book = $this->getBookService->execute($id);

        $coverKey = $book->coverKey();
        if ($coverKey === null) {
            return response()->json([
                'code' => 'NOT_FOUND',
                'message' => 'El libro no tiene una portada asignada.',
                'details' => [],
            ], 404);
        }

        $fileData = $this->coverStorage->get($coverKey);
        if ($fileData === null) {
            return response()->json([
                'code' => 'NOT_FOUND',
                'message' => 'El archivo de portada no fue encontrado en el almacenamiento.',
                'details' => [],
            ], 404);
        }

        return new \Symfony\Component\HttpFoundation\Response($fileData['content'], 200, [
            'Content-Type' => $fileData['mime_type'],
        ]);
    }

    public function upload(Request $request, string $id): JsonResponse
    {
        $file = $request->file('file');

        if ($file === null || !$file->isValid()) {
            throw new ValidationException(
                message: 'No se envió un archivo de portada válido.',
                details: [['field' => 'file', 'issue' => 'archivo requerido']]
            );
        }

        $binaryContent = file_get_contents($file->getRealPath());
        if ($binaryContent === false) {
            throw new ValidationException(
                message: 'No se pudo leer el contenido del archivo de portada.',
                details: [['field' => 'file', 'issue' => 'error de lectura del archivo']]
            );
        }

        // Determinar MIME por contenido
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $binaryContent) ?: $file->getMimeType() ?: 'application/octet-stream';
        finfo_close($finfo);

        $coverKey = $this->uploadCoverService->execute(
            bookId: $id,
            binaryContent: $binaryContent,
            mimeType: $mimeType
        );

        return response()->json([
            'cover_key' => $coverKey,
        ], 200);
    }

    public function destroy(string $id): Response
    {
        $this->deleteCoverService->execute($id);

        return response()->noContent(204);
    }
}
