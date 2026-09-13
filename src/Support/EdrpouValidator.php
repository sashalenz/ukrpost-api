<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\Support;

final class EdrpouValidator
{
    public static function isValid(string $value): bool
    {
        if (! preg_match('/^\d{5,8}$/', $value)) {
            return false;
        }

        // Ukrposhta accepts five-to-eight digits, while the published checksum is defined over eight positions.
        $normalized = str_pad($value, 8, '0', STR_PAD_LEFT);
        $number = (int) $normalized;
        $weights = $number > 30000000 && $number < 60000000
            ? [7, 1, 2, 3, 4, 5, 6]
            : [1, 2, 3, 4, 5, 6, 7];
        $check = self::checksum($normalized, $weights);

        if ($check === 10) {
            $check = self::checksum($normalized, array_map(
                static fn (int $weight): int => $weight + 2,
                $weights,
            ));
        }

        return $check < 10 && $check === (int) $normalized[7];
    }

    /** @param list<int> $weights */
    private static function checksum(string $value, array $weights): int
    {
        $sum = 0;

        foreach ($weights as $position => $weight) {
            $sum += (int) $value[$position] * $weight;
        }

        return $sum % 11;
    }
}
