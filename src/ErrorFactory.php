<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi;

use Sashalenz\UkrPostApi\Exceptions\UkrPostApiUnavailableException;
use Sashalenz\UkrPostApi\Exceptions\UkrPostAuthenticationException;
use Sashalenz\UkrPostApi\Exceptions\UkrPostDocumentException;
use Sashalenz\UkrPostApi\Exceptions\UkrPostException;
use Sashalenz\UkrPostApi\Exceptions\UkrPostNotFoundException;
use Sashalenz\UkrPostApi\Exceptions\UkrPostValidationException;

final class ErrorFactory
{
    public static function make(int $status, ?string $code, string $message, ?\Throwable $previous = null): UkrPostException
    {
        $exception = match (true) {
            $code !== null && str_starts_with($code, 'UPE05'), $status === 401, $status === 403 => UkrPostAuthenticationException::class,
            $code !== null && str_starts_with($code, 'UPE02'), $status === 404 => UkrPostNotFoundException::class,
            $code !== null && str_starts_with($code, 'UPE04') => UkrPostDocumentException::class,
            $code !== null && (str_starts_with($code, 'UPE06') || str_starts_with($code, 'UPE08')), $status >= 500 => UkrPostApiUnavailableException::class,
            $code !== null && (str_starts_with($code, 'UPE01') || str_starts_with($code, 'UPE03') || str_starts_with($code, 'UPE07')), $status === 400, $status === 422 => UkrPostValidationException::class,
            default => UkrPostException::class,
        };

        return new $exception($message, $code, $previous);
    }
}
