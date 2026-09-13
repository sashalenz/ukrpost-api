<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\Exceptions;

use RuntimeException;

class UkrPostException extends RuntimeException
{
    public function __construct(string $message = '', public readonly ?string $errorCode = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
