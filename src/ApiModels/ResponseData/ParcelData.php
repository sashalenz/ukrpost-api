<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels\ResponseData;

use Spatie\LaravelData\Data;

final class ParcelData extends Data
{
    /** @param list<ParcelItemData> $parcelItems */
    public function __construct(
        public ?string $uuid = null,
        public ?string $barcode = null,
        public ?int $weight = null,
        public ?int $length = null,
        public ?int $width = null,
        public ?int $height = null,
        public ?float $declaredPrice = null,
        public ?float $postPay = null,
        public ?string $name = null,
        public ?string $description = null,
        public ?int $parcelNumber = null,
        public array $parcelItems = [],
    ) {}
}
