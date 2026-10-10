<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Application\Ports\Outbound\CoverStorageInterface;

final class FakeCoverStorage implements CoverStorageInterface
{
    /**
     * @var array<string, array{content: string, mime_type: string}>
     */
    private array $storage = [];

    public function store(string $bookId, string $binaryContent, string $mimeType): string
    {
        $key = 'covers/' . $bookId . '_' . bin2hex(random_bytes(4));
        $this->storage[$key] = [
            'content' => $binaryContent,
            'mime_type' => $mimeType,
        ];

        return $key;
    }

    public function get(string $coverKey): ?array
    {
        return $this->storage[$coverKey] ?? null;
    }

    public function delete(string $coverKey): void
    {
        unset($this->storage[$coverKey]);
    }

    public function has(string $coverKey): bool
    {
        return isset($this->storage[$coverKey]);
    }
}
