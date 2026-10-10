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
        \Illuminate\Support\Facades\Log::info('Archivos recibidos:', $request->allFiles());

        $file = $request->file('cover') ?? $request->file('file');

        if ($file === null || !$file->isValid()) {
            throw new ValidationException(
                message: 'No se envió un archivo de portada válido.',
                details: [['field' => 'cover', 'issue' => 'El campo cover o file es requerido y debe ser un archivo válido.']]
            );
        }

        // Validar tamaño máximo 2048 KB (2MB)
        if ($file->getSize() > 2048 * 1024) {
            throw new ValidationException(
                message: 'El archivo de portada excede el tamaño máximo permitido de 2 MB.',
                details: [['field' => 'cover', 'issue' => 'El tamaño debe ser de máximo 2048 KB.']]
            );
        }

        // Validar tipos MIME / extensiones de imagen soportados
        $mimeType = $file->getMimeType();
        $clientExtension = strtolower($file->getClientOriginalExtension());
        $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
        $allowedExts = ['jpeg', 'png', 'jpg', 'webp'];

        if (!in_array($mimeType, $allowedMimes, true) && !in_array($clientExtension, $allowedExts, true)) {
            throw new ValidationException(
                message: 'El archivo debe ser una imagen válida (jpeg, png, jpg, webp).',
                details: [['field' => 'cover', 'issue' => 'Formato de imagen no soportado.']]
            );
        }

        $binaryContent = file_get_contents($file->getRealPath());
        if ($binaryContent === false) {
            throw new ValidationException(
                message: 'No se pudo leer el contenido del archivo de portada.',
                details: [['field' => 'cover', 'issue' => 'Error al leer el archivo.']]
            );
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_buffer($finfo, $binaryContent) ?: $mimeType ?: 'image/jpeg';
        finfo_close($finfo);

        $coverKey = $this->uploadCoverService->execute(
            bookId: $id,
            binaryContent: $binaryContent,
            mimeType: $detectedMime
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
