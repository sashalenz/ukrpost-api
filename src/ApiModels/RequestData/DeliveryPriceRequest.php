<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels\RequestData;

use Sashalenz\UkrPostApi\Enums\DeliveryType;
use Sashalenz\UkrPostApi\Enums\ShipmentType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class DeliveryPriceRequest extends Data
{
    /**
     * @param  list<DeliveryParcelRequestData|array<string, mixed>>  $parcels
     * @param  list<DiscountRequestData|array<string, mixed>>  $discounts
     */
    public function __construct(
        public string $addressFromPostcode,
        public string $addressToPostcode,
        public ShipmentType $type,
        public DeliveryType $deliveryType,
        public array $parcels,
        public array $discounts = [],
        public bool $validate = false,
        public float|null|Optional $postPay = new Optional,
        public float|null|Optional $declaredPrice = new Optional,
        public bool|null|Optional $packagingPaidByRecipient = new Optional,
        public bool $transferPostPayToCard = false,
        public bool $sms = false,
        public bool $documentBack = false,
        public DeliveryType|null|Optional $documentBackDeliveryType = new Optional,
        public bool $withDeliveryNotification = false,
        public bool $withEmailDeliveryNotification = false,
        public bool $listOfEnclosedItems = false,
        public bool $ascentToTheFloor = false,
        public bool|null|Optional $lift = new Optional,
        public int|null|Optional $floor = new Optional,
        public float|null|Optional $lengthOverpayRatio = new Optional,
    ) {}

    /** @return array<string, mixed> */
    public function toApiArray(): array
    {
        $parcels = array_map(
            static fn (DeliveryParcelRequestData|array $parcel): array => $parcel instanceof DeliveryParcelRequestData
                ? $parcel->toApiArray()
                : $parcel,
            $this->parcels,
        );
        $discounts = array_map(
            static fn (DiscountRequestData|array $discount): array => $discount instanceof DiscountRequestData
                ? $discount->toApiArray()
                : $discount,
            $this->discounts,
        );

        return array_filter([
            'addressFrom' => ['postcode' => $this->addressFromPostcode],
            'addressTo' => ['postcode' => $this->addressToPostcode],
            'type' => $this->type->value,
            'deliveryType' => $this->deliveryType->value,
            'parcels' => $parcels,
            'discounts' => $discounts,
            'validate' => $this->validate,
            'postPay' => $this->postPay,
            'declaredPrice' => $this->declaredPrice,
            'packagingPaidByRecipient' => $this->packagingPaidByRecipient,
            'transferPostPayToCard' => $this->transferPostPayToCard,
            'sms' => $this->sms,
            'documentBack' => $this->documentBack,
            'documentBackDeliveryType' => $this->documentBackDeliveryType instanceof DeliveryType
                ? $this->documentBackDeliveryType->value
                : $this->documentBackDeliveryType,
            'withDeliveryNotification' => $this->withDeliveryNotification,
            'withEmailDeliveryNotification' => $this->withEmailDeliveryNotification,
            'listOfEnclosedItems' => $this->listOfEnclosedItems,
            'ascentToTheFloor' => $this->ascentToTheFloor,
            'lift' => $this->lift,
            'floor' => $this->floor,
            'lengthOverpayRatio' => $this->lengthOverpayRatio,
        ], static fn (mixed $value): bool => ! $value instanceof Optional);
    }
}
