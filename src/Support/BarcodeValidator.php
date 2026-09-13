<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\Support;

use Sashalenz\UkrPostApi\Exceptions\UkrPostValidationException;

final class BarcodeValidator
{
    public static function isTrackable(string $barcode): bool
    {
        $barcode = strtoupper(trim($barcode));
        $prefix = substr($barcode, 0, 1);
        $country = substr($barcode, -2);

        return match ($prefix) {
            'U' => $country === 'UA',
            'L' => in_array($country, ['UA', 'CN'], true),
            default => true,
        };
    }

    public static function ensureTrackable(string $barcode): void
    {
        if (! self::isTrackable($barcode)) {
            throw new UkrPostValidationException('The barcode is not supported by Ukrposhta tracking.');
        }
    }
}
