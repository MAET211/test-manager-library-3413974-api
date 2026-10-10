<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Application\Ports\Outbound\CoverStorageInterface;

final class DiskCoverStorage implements CoverStorageInterface
{
    private string $baseDirectory;

    public function __construct(?string $baseDirectory = null)
    {
        $this->baseDirectory = $baseDirectory ?? storage_path('app/covers');
        if (!is_dir($this->baseDirectory)) {
            @mkdir($this->baseDirectory, 0755, true);
        }
    }

    public function store(string $bookId, string $binaryContent, string $mimeType): string
    {
        $ext = $mimeType === 'image/png' ? 'png' : 'jpg';
        $random = bin2hex(random_bytes(8));
        $filename = "{$bookId}_{$random}.{$ext}";
        $fullPath = $this->baseDirectory . DIRECTORY_SEPARATOR . $filename;

        file_put_contents($fullPath, $binaryContent);

        return "covers/{$filename}";
    }

    public function get(string $coverKey): ?array
    {
        $filename = basename($coverKey);
        $fullPath = $this->baseDirectory . DIRECTORY_SEPARATOR . $filename;

        if (!file_exists($fullPath)) {
            return null;
        }

        $content = file_get_contents($fullPath);
        if ($content === false) {
            return null;
        }

        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $mime = $ext === 'png' ? 'image/png' : 'image/jpeg';

        return [
            'content' => $content,
            'mime_type' => $mime,
        ];
    }

    public function delete(string $coverKey): void
    {
        $filename = basename($coverKey);
        $fullPath = $this->baseDirectory . DIRECTORY_SEPARATOR . $filename;

        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
    }
}
