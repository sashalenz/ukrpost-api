<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\Support;

use Sashalenz\UkrPostApi\Exceptions\UkrPostValidationException;

final class AddressConstraints
{
    /** @param array<string, mixed> $data */
    public static function validate(array $data, bool $postcodeRequired = true): void
    {
        $postcode = $data['postcode'] ?? null;

        if (($postcodeRequired || $postcode !== null) && (! is_string($postcode) || ! preg_match('/^\d{5}$/', $postcode))) {
            throw new UkrPostValidationException('postcode must contain exactly five digits.');
        }

        if (isset($data['floor']) && (! is_int($data['floor']) || $data['floor'] < 0 || $data['floor'] > 57)) {
            throw new UkrPostValidationException('floor must be between 0 and 57.');
        }

        if (self::filled($data['mailbox'] ?? null) && (self::filled($data['street'] ?? null) || self::filled($data['houseNumber'] ?? null) || self::filled($data['apartmentNumber'] ?? null))) {
            throw new UkrPostValidationException('mailbox cannot be combined with street, houseNumber, or apartmentNumber.');
        }
    }

    private static function filled(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
