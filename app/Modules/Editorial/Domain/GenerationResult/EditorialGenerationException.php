<?php

namespace App\Modules\Editorial\Domain\GenerationResult;

use RuntimeException;
use Throwable;

/**
 * Typed Editorial generation failure with a stable error code.
 */
final class EditorialGenerationException extends RuntimeException
{
    public function __construct(
        private readonly string $errorCode,
        string $message = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message !== '' ? $message : $errorCode, 0, $previous);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public static function permanent(string $errorCode, string $message = '', ?Throwable $previous = null): self
    {
        return new self($errorCode, $message !== '' ? $message : $errorCode, $previous);
    }
}
