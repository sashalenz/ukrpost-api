<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels\ResponseData;

use Sashalenz\UkrPostApi\Enums\CourierInterval;
use Sashalenz\UkrPostApi\Enums\CourierOrderStatus;
use Sashalenz\UkrPostApi\Enums\CourierOrderType;
use Spatie\LaravelData\Data;

final class CourierOrderData extends Data
{
    /**
     * @param  list<string>  $shipmentBarcodes
     * @param  list<string>  $letterBarcodes
     */
    public function __construct(
        public ?string $uuid = null,
        public ?int $orderNumber = null,
        public ?string $clientUuid = null,
        public ?CourierOrderType $type = null,
        public ?int $addressId = null,
        public ?int $phoneId = null,
        public ?string $email = null,
        public ?string $dropDate = null,
        public ?CourierInterval $interval = null,
        public array $shipmentBarcodes = [],
        public array $letterBarcodes = [],
        public ?CourierOrderStatus $lastStatus = null,
        public ?string $lastStatusDate = null,
        public ?string $courierLastName = null,
        public ?string $courierFirstName = null,
        public ?string $courierMiddleName = null,
        public ?string $courierPhoneNumber = null,
        public ?string $carNumber = null,
        public ?string $carType = null,
    ) {}
}
