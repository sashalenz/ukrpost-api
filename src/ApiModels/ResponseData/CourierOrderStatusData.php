<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels\ResponseData;

use Sashalenz\UkrPostApi\Enums\CourierOrderStatus;
use Spatie\LaravelData\Data;

final class CourierOrderStatusData extends Data
{
    public function __construct(
        public ?string $uuid = null,
        public ?string $orderUuid = null,
        public ?string $statusDate = null,
        public ?CourierOrderStatus $orderStatus = null,
    ) {}
}
