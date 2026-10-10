<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

abstract class DomainException extends \Exception
{
    /**
     * @param array<int, array{field: string, issue: string}> $details
     */
    public function __construct(
        protected string $errorCode,
        string $message,
        protected int $httpStatusCode = 400,
        protected array $details = []
    ) {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    /**
     * @return array<int, array{field: string, issue: string}>
     */
    public function getDetails(): array
    {
        return $this->details;
    }
}
