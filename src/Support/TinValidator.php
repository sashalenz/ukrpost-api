<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\Support;

final class TinValidator
{
    public static function isValid(string $value): bool
    {
        if (! preg_match('/^\d{10}$/', $value)) {
            return false;
        }

        $sum = 0;

        foreach ([-1, 5, 7, 9, 4, 6, 10, 5, 7] as $position => $weight) {
            $sum += (int) $value[$position] * $weight;
        }

        // PHP keeps the sign for a negative remainder; normalize it before the second modulus.
        $check = (($sum % 11) + 11) % 11 % 10;

        return $check === (int) $value[9];
    }
}
