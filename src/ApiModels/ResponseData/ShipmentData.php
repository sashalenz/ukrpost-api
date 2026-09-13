<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels\ResponseData;

use Sashalenz\UkrPostApi\Enums\DeliveryType;
use Sashalenz\UkrPostApi\Enums\OnFailReceiveType;
use Sashalenz\UkrPostApi\Enums\ShipmentStatus;
use Sashalenz\UkrPostApi\Enums\ShipmentType;
use Spatie\LaravelData\Data;

final class ShipmentData extends Data
{
    /** @param list<ParcelData> $parcels */
    public function __construct(
        public ?string $uuid = null,
        public ?string $barcode = null,
        public ?DeliveryType $deliveryType = null,
        public ?ShipmentType $type = null,
        public ?ShipmentStatus $status = null,
        public ?float $deliveryPrice = null,
        public ?float $rawDeliveryPrice = null,
        public ?float $postPay = null,
        public ?float $declaredPrice = null,
        public ?float $postPayDeliveryPrice = null,
        public ?float $returnDeliveryPrice = null,
        public ?bool $priceChangedInPostOffice = null,
        public ?string $calculationDescription = null,
        public ?string $lastModified = null,
        public ?string $deliveryDate = null,
        public ?bool $postPayPaidByRecipient = null,
        public ?int $returnAfterStorageDays = null,
        public ?OnFailReceiveType $onFailReceiveType = null,
        public ?bool $personalHanding = null,
        public ?ClientData $sender = null,
        public ?ClientData $recipient = null,
        public array $parcels = [],
    ) {}
}
