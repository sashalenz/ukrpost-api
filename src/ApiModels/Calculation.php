<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels;

use Sashalenz\UkrPostApi\ApiModels\RequestData\DeliveryPriceRequest;
use Sashalenz\UkrPostApi\ApiModels\ResponseData\DeliveryPriceData;
use Sashalenz\UkrPostApi\Enums\ShipmentType;
use Sashalenz\UkrPostApi\Exceptions\UkrPostValidationException;
use Sashalenz\UkrPostApi\Support\CalculationConstraints;

final class Calculation extends BaseModel
{
    /** @param DeliveryPriceRequest|array<string, mixed> $data */
    public function domestic(DeliveryPriceRequest|array $data): DeliveryPriceData
    {
        $payload = $data instanceof DeliveryPriceRequest ? $data->toApiArray() : $data;
        CalculationConstraints::validate($payload);

        return DeliveryPriceData::from($this->post('/domestic/delivery-price', $payload)->all());
    }

    /** @param DeliveryPriceRequest|array<string, mixed> $data */
    public function multiParcel(DeliveryPriceRequest|array $data): DeliveryPriceData
    {
        $payload = $data instanceof DeliveryPriceRequest ? $data->toApiArray() : $data;
        $parcels = $payload['parcels'] ?? null;
        if (! is_array($parcels) || count($parcels) < 2) {
            throw new UkrPostValidationException('A multi-parcel calculation requires at least two parcels.');
        }

        return $this->domestic($payload);
    }

    /** @param DeliveryPriceRequest|array<string, mixed> $data */
    public function document(DeliveryPriceRequest|array $data): DeliveryPriceData
    {
        $payload = $data instanceof DeliveryPriceRequest ? $data->toApiArray() : $data;
        if (($payload['type'] ?? null) !== ShipmentType::DOCUMENT->value) {
            throw new UkrPostValidationException('A document calculation requires DOCUMENT shipment type.');
        }

        $parcels = $payload['parcels'] ?? null;
        if (! is_array($parcels) || count($parcels) !== 1) {
            throw new UkrPostValidationException('A document calculation requires exactly one parcel.');
        }

        if (($payload['postPay'] ?? 0) !== 0) {
            throw new UkrPostValidationException('DOCUMENT shipments cannot use postPay.');
        }

        return $this->domestic($payload);
    }
}
