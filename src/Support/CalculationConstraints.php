<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\Support;

use Sashalenz\UkrPostApi\Enums\ShipmentType;
use Sashalenz\UkrPostApi\Exceptions\UkrPostValidationException;

final class CalculationConstraints
{
    /** @param array<string, mixed> $payload */
    public static function validate(array $payload): void
    {
        foreach (['addressFrom', 'addressTo'] as $addressKey) {
            $address = $payload[$addressKey] ?? null;
            $postcode = is_array($address) ? ($address['postcode'] ?? null) : null;
            if (! is_string($postcode) || preg_match('/^\d{5}$/', $postcode) !== 1) {
                throw new UkrPostValidationException($addressKey.'.postcode must contain five digits.');
            }
        }

        $type = $payload['type'] ?? null;
        if (! is_string($type) || ShipmentType::tryFrom($type) === null || $type === ShipmentType::VALUABLE_LETTER->value) {
            throw new UkrPostValidationException('Unsupported domestic calculation shipment type.');
        }

        $parcels = $payload['parcels'] ?? null;
        if (! is_array($parcels) || $parcels === []) {
            throw new UkrPostValidationException('At least one parcel is required for calculation.');
        }

        foreach ($parcels as $parcel) {
            if (! is_array($parcel)) {
                throw new UkrPostValidationException('Every calculated parcel must be an object.');
            }

            foreach (['weight', 'length', 'width', 'height'] as $field) {
                if (! is_int($parcel[$field] ?? null) || $parcel[$field] < 1) {
                    throw new UkrPostValidationException('Parcel '.$field.' must be a positive integer.');
                }
            }

            self::nonNegative($parcel['packagingPrice'] ?? null, 'packagingPrice');
        }

        foreach (['postPay', 'declaredPrice', 'lengthOverpayRatio'] as $field) {
            self::nonNegative($payload[$field] ?? null, $field);
        }

        $discounts = $payload['discounts'] ?? [];
        if (! is_array($discounts)) {
            throw new UkrPostValidationException('discounts must be a list.');
        }

        foreach ($discounts as $discount) {
            $rate = is_array($discount) ? ($discount['rate'] ?? null) : null;
            if (! is_int($rate) && ! is_float($rate)) {
                throw new UkrPostValidationException('Every discount requires a numeric rate.');
            }

            self::nonNegative($rate, 'discount rate');
        }

        if (($payload['documentBack'] ?? false) === true && ! is_string($payload['documentBackDeliveryType'] ?? null)) {
            throw new UkrPostValidationException('documentBackDeliveryType is required when documentBack is enabled.');
        }
    }

    private static function nonNegative(mixed $value, string $field): void
    {
        if ($value === null) {
            return;
        }

        if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value) || $value < 0) {
            throw new UkrPostValidationException($field.' must be a finite non-negative number.');
        }
    }
}
