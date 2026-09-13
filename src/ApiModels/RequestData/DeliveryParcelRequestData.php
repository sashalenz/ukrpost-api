<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels\RequestData;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class DeliveryParcelRequestData extends Data
{
    public function __construct(
        public int $weight,
        public int $length,
        public int $width,
        public int $height,
        public float|null|Optional $packagingPrice = new Optional,
        public bool|null|Optional $packagingPaidByRecipient = new Optional,
    ) {}

    /** @return array<string, mixed> */
    public function toApiArray(): array
    {
        return array_filter([
            'weight' => $this->weight,
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
            'packagingPrice' => $this->packagingPrice,
            'packagingPaidByRecipient' => $this->packagingPaidByRecipient,
        ], static fn (mixed $value): bool => ! $value instanceof Optional);
    }
}
