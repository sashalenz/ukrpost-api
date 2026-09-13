<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels\ResponseData;

use Sashalenz\UkrPostApi\Enums\EventCode;
use Sashalenz\UkrPostApi\Enums\ShipmentStatus;
use Spatie\LaravelData\Data;

final class StatusData extends Data
{
    public function __construct(
        public ?string $barcode = null,
        public ?int $step = null,
        public ?string $date = null,
        public ?string $index = null,
        public ?string $name = null,
        public ?int $event = null,
        public ?string $eventName = null,
        public ?string $country = null,
        public ?string $eventReason = null,
        public ?int $eventReason_id = null,
        public ?int $mailType = null,
        public ?int $indexOrder = null,
        public ?int $height = null,
        public ?int $length = null,
        public ?int $width = null,
        public ?int $weight = null,
        public ?float $payment = null,
        public ?float $sendingPaymentSum = null,
        public ?int $sendingPayerCode = null,
        public ?string $indexFrom = null,
        public ?string $indexTo = null,
        public ?string $addresseePhoneNum = null,
    ) {}

    public function shipmentStatus(): ShipmentStatus
    {
        if ($this->event === null) {
            throw new \LogicException('A tracking response without an event cannot be mapped to a shipment status.');
        }

        return EventCode::toStatus($this->event, $this->eventReason_id);
    }
}
