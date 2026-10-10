<?php

declare(strict_types=1);

namespace App\Application\Ports\Outbound;

interface CoverStorageInterface
{
    public function store(string $bookId, string $binaryContent, string $mimeType): string;

    /**
     * @return array{content: string, mime_type: string}|null
     */
    public function get(string $coverKey): ?array;

    public function delete(string $coverKey): void;
}
