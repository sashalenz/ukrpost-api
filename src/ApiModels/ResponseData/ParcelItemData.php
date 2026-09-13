<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels\ResponseData;

use Spatie\LaravelData\Data;

final class ParcelItemData extends Data
{
    public function __construct(
        public ?string $name = null,
        public ?float $value = null,
        public ?int $quantity = null,
        public ?int $parcelItemNumber = null,
    ) {}
}
