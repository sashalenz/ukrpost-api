<?php

declare(strict_types=1);

use Sashalenz\UkrPostApi\Support\EdrpouValidator;
use Sashalenz\UkrPostApi\Support\TinValidator;

it('validates EDRPOU length, range-specific weights, and checksum', function (): void {
    expect(EdrpouValidator::isValid('32855961'))->toBeTrue()
        ->and(EdrpouValidator::isValid('10004'))->toBeTrue()
        ->and(EdrpouValidator::isValid('10000062'))->toBeTrue()
        ->and(EdrpouValidator::isValid('32855962'))->toBeFalse()
        ->and(EdrpouValidator::isValid('1234'))->toBeFalse()
        ->and(EdrpouValidator::isValid('123456789'))->toBeFalse();
});

it('validates RNOKPP checksum instead of format alone', function (): void {
    expect(TinValidator::isValid('3000607991'))->toBeTrue()
        ->and(TinValidator::isValid('3000607994'))->toBeFalse()
        ->and(TinValidator::isValid('300060799'))->toBeFalse();
});
