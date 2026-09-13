<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\Support;

use Sashalenz\UkrPostApi\Enums\ClientType;
use Sashalenz\UkrPostApi\Enums\DeliveryType;
use Sashalenz\UkrPostApi\Enums\PostPayPaymentType;
use Sashalenz\UkrPostApi\Enums\ShipmentType;
use Sashalenz\UkrPostApi\Exceptions\UkrPostValidationException;

final class ShipmentConstraints
{
    /** @param array<string, mixed> $data */
    public static function validate(array $data, ?ShipmentValidationContext $context = null): void
    {
        $context ??= new ShipmentValidationContext;
        $delivery = self::deliveryType($data['deliveryType'] ?? null);
        $type = self::shipmentType($data['type'] ?? ShipmentType::EXPRESS->value);
        $parcels = self::parcels($data['parcels'] ?? null);

        self::validateParcels($parcels, $delivery, $type, $context->recipientPostOfficeMobile);
        self::validatePostPay($data, $parcels, $type, $context);
        self::validateRecipientAddress($delivery, $data, $context->recipientAddress);
    }

    public static function validatePostPayChange(float $postPay, float $declaredPrice): void
    {
        // Zero cancels postpay; the same management endpoint handles both correction and cancellation.
        if (! is_finite($postPay) || $postPay < 0 || $postPay > 29999) {
            throw new UkrPostValidationException('Changed postPay must be between 0 and 29999 UAH.');
        }

        if (! is_finite($declaredPrice) || $declaredPrice < 0 || $postPay > $declaredPrice) {
            throw new UkrPostValidationException('Changed postPay cannot exceed declaredPrice.');
        }
    }

    /** @param list<array<string, mixed>> $parcels */
    private static function validateParcels(array $parcels, DeliveryType $delivery, ShipmentType $type, bool $mobilePostOffice): void
    {
        if ($type === ShipmentType::CARGO && count($parcels) !== 1) {
            throw new UkrPostValidationException('Cargo shipment must contain exactly one parcel.');
        }

        if ($type === ShipmentType::DOCUMENT && count($parcels) !== 1) {
            throw new UkrPostValidationException('Document shipment must contain exactly one parcel.');
        }

        $totalWeight = 0;
        $hasDeclaredPrice = false;
        foreach ($parcels as $parcel) {
            $weight = self::positiveInteger($parcel, 'weight');
            $length = self::positiveInteger($parcel, 'length');
            $width = self::positiveInteger($parcel, 'width');
            $height = self::positiveInteger($parcel, 'height');
            $totalWeight += $weight;
            $largestSide = max($length, $width, $height);
            if ($weight > 30000) {
                throw new UkrPostValidationException('A parcel cannot weigh more than 30000 grams.');
            }

            if (in_array($type, [ShipmentType::EXPRESS, ShipmentType::STANDARD], true) && ($length > 120 || $width > 70 || $height > 70)) {
                throw new UkrPostValidationException('Parcel dimensions exceed Express/Standard limits.');
            }

            if ($type === ShipmentType::CARGO && $largestSide < 120) {
                throw new UkrPostValidationException('Cargo parcel must have a side of at least 120 cm.');
            }

            if ($type === ShipmentType::CARGO && ($length + $width + $height < 250 || $length + $width + $height > 350)) {
                throw new UkrPostValidationException('Cargo parcel dimensions must sum to between 250 and 350 cm.');
            }

            $declaredPrice = $parcel['declaredPrice'] ?? null;
            if ($declaredPrice !== null && ((! is_int($declaredPrice) && ! is_float($declaredPrice)) || ! is_finite((float) $declaredPrice) || $declaredPrice < 0)) {
                throw new UkrPostValidationException('Parcel declaredPrice must be a non-negative number.');
            }
            $hasDeclaredPrice = $hasDeclaredPrice || $declaredPrice !== null;

            if ($type === ShipmentType::DOCUMENT && is_numeric($declaredPrice) && (float) $declaredPrice > 300) {
                throw new UkrPostValidationException('Document parcel declared price cannot exceed 300 UAH.');
            }
        }

        if ($totalWeight > 1000000) {
            throw new UkrPostValidationException('Total parcel weight exceeds 1000000 grams.');
        }

        if (count($parcels) > 1 && ! $hasDeclaredPrice) {
            throw new UkrPostValidationException('A multi-parcel shipment requires declaredPrice on at least one parcel.');
        }

        if (count($parcels) > 5 && ($delivery->usesCourier() || $mobilePostOffice)) {
            throw new UkrPostValidationException('This delivery supports no more than five parcels.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $parcels
     */
    private static function validatePostPay(array $data, array $parcels, ShipmentType $type, ShipmentValidationContext $context): void
    {
        $postPay = $data['postPay'] ?? 0;

        if (! is_int($postPay) && ! is_float($postPay)) {
            throw new UkrPostValidationException('postPay must be a number.');
        }

        if (! is_finite((float) $postPay) || $postPay < 0) {
            throw new UkrPostValidationException('postPay cannot be negative or non-finite.');
        }

        if ($postPay === 0 || $postPay === 0.0) {
            return;
        }

        if ($type === ShipmentType::DOCUMENT) {
            throw new UkrPostValidationException('Document shipment cannot have postPay.');
        }

        $declaredPrice = array_reduce(
            $parcels,
            static fn (float $sum, array $parcel): float => $sum + (is_int($parcel['declaredPrice'] ?? null) || is_float($parcel['declaredPrice'] ?? null) ? (float) $parcel['declaredPrice'] : 0.0),
            0.0,
        );
        if ($postPay > $declaredPrice) {
            throw new UkrPostValidationException('postPay cannot exceed the shipment declared price.');
        }

        $sender = $context->sender ?? self::arrayValue($data['sender'] ?? null) ?? [];
        $recipient = $context->recipient ?? self::arrayValue($data['recipient'] ?? null) ?? [];
        $senderType = self::validatePostPayClient($sender, 'sender');
        $recipientType = self::clientType($recipient, 'recipient');

        if (in_array($recipientType, [ClientType::COMPANY, ClientType::PRIVATE_ENTREPRENEUR], true)) {
            throw new UkrPostValidationException('A company or entrepreneur recipient cannot receive a postPay shipment.');
        }

        $cashless = ($data['transferPostPayToBankAccount'] ?? false) === true
            || ($sender['postPayPaymentType'] ?? null) === PostPayPaymentType::CASHLESS_ONLY->value;
        $limit = $cashless ? 100000 : 50000;

        if ($postPay > $limit) {
            throw new UkrPostValidationException(sprintf('postPay cannot exceed %d UAH for this payment method.', $limit));
        }

        if (($data['transferPostPayToBankAccount'] ?? false) === true && $senderType === ClientType::INDIVIDUAL) {
            throw new UkrPostValidationException('Bank transfer requires a company or entrepreneur sender.');
        }

        if (in_array($senderType, [ClientType::COMPANY, ClientType::PRIVATE_ENTREPRENEUR], true) && ! $cashless) {
            throw new UkrPostValidationException('A company or entrepreneur sender can receive postPay only by bank transfer.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $resolvedAddress
     */
    private static function validateRecipientAddress(DeliveryType $delivery, array $data, ?array $resolvedAddress): void
    {
        $address = $resolvedAddress ?? self::arrayValue($data['recipientAddress'] ?? null);
        $recipient = self::arrayValue($data['recipient'] ?? null);
        $address ??= self::arrayValue($recipient['address'] ?? null);

        if ($address === null) {
            return;
        }

        $mailbox = self::filled($address['mailbox'] ?? null);
        $street = self::filled($address['street'] ?? null);
        $houseNumber = self::filled($address['houseNumber'] ?? null);
        $apartmentNumber = self::filled($address['apartmentNumber'] ?? null);

        if ($mailbox && ($street || $houseNumber || $apartmentNumber || $delivery->hasCourierDelivery())) {
            throw new UkrPostValidationException('mailbox cannot be combined with a street address or courier delivery.');
        }

        if ($delivery->hasCourierDelivery() && (! $street || ! $houseNumber)) {
            throw new UkrPostValidationException('street and houseNumber are required for courier delivery.');
        }
    }

    /** @param array<string, mixed> $client */
    private static function validatePostPayClient(array $client, string $role): ClientType
    {
        $type = self::clientType($client, $role);

        if ($type === ClientType::INDIVIDUAL && ! self::filled($client['middleName'] ?? null)) {
            throw new UkrPostValidationException($role.' middleName is required for postPay.');
        }

        return $type;
    }

    /** @param array<string, mixed> $client */
    private static function clientType(array $client, string $role): ClientType
    {
        $typeValue = $client['type'] ?? null;
        $type = $typeValue instanceof ClientType ? $typeValue : (is_string($typeValue) ? ClientType::tryFrom($typeValue) : null);

        return $type ?? throw new UkrPostValidationException($role.' client type is missing or invalid.');
    }

    /** @return list<array<string, mixed>> */
    private static function parcels(mixed $value): array
    {
        if (! is_array($value) || $value === []) {
            throw new UkrPostValidationException('At least one parcel is required.');
        }

        $parcels = [];

        foreach ($value as $parcel) {
            $normalized = self::arrayValue($parcel);
            if ($normalized === null) {
                throw new UkrPostValidationException('Each parcel must be an array.');
            }

            $parcels[] = $normalized;
        }

        return $parcels;
    }

    /** @param array<string, mixed> $parcel */
    private static function positiveInteger(array $parcel, string $field): int
    {
        $value = $parcel[$field] ?? null;

        if (! is_int($value) || $value <= 0) {
            throw new UkrPostValidationException($field.' must be a positive integer.');
        }

        return $value;
    }

    private static function deliveryType(mixed $value): DeliveryType
    {
        $delivery = $value instanceof DeliveryType ? $value : (is_string($value) ? DeliveryType::tryFrom($value) : null);

        return $delivery ?? throw new UkrPostValidationException('Invalid deliveryType.');
    }

    private static function shipmentType(mixed $value): ShipmentType
    {
        $type = $value instanceof ShipmentType ? $value : (is_string($value) ? ShipmentType::tryFrom($value) : null);

        return $type ?? throw new UkrPostValidationException('Invalid shipment type.');
    }

    /** @return array<string, mixed>|null */
    private static function arrayValue(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $result = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                return null;
            }

            $result[$key] = $item;
        }

        return $result;
    }

    private static function filled(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
