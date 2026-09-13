<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels\ResponseData;

use Sashalenz\UkrPostApi\Enums\GroupType;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

final class ShipmentGroupData extends Data
{
    public function __construct(
        public ?string $uuid = null,
        public ?string $name = null,
        public ?GroupType $type = null,
        public ?string $clientUuid = null,
        public ?string $counterpartyUuid = null,
        public ?string $counterpartyRegcode = null,
        public ?string $created = null,
        #[MapInputName('barcode_g_id')]
        public ?string $barcodeGId = null,
        public ?bool $byCourier = null,
        public ?bool $closed = null,
    ) {}
}
