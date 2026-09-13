<?php

declare(strict_types=1);

use Sashalenz\UkrPostApi\ErrorFactory;
use Sashalenz\UkrPostApi\Exceptions\UkrPostAuthenticationException;
use Sashalenz\UkrPostApi\Exceptions\UkrPostNotFoundException;
use Sashalenz\UkrPostApi\Exceptions\UkrPostValidationException;

it('maps API errors to typed exceptions', function (): void {
    expect(ErrorFactory::make(401, 'UPE05001', 'bad'))->toBeInstanceOf(UkrPostAuthenticationException::class)
        ->and(ErrorFactory::make(404, 'UPE02000', 'missing'))->toBeInstanceOf(UkrPostNotFoundException::class)
        ->and(ErrorFactory::make(422, null, 'invalid'))->toBeInstanceOf(UkrPostValidationException::class);
});
