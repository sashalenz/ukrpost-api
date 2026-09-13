<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels\RequestData;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class ParcelRequestData extends Data
{
    /** @param array<int, array<string, mixed>>|null|Optional $parcelItems */
    public function __construct(
        public int $weight,
        public int $length,
        public int $width,
        public int $height,
        public float|null|Optional $declaredPrice = new Optional,
        public string|null|Optional $name = new Optional,
        public string|null|Optional $description = new Optional,
        public float|null|Optional $postPay = new Optional,
        public array|null|Optional $parcelItems = new Optional,
    ) {}

    /** @return array<string, mixed> */
    public function toApiArray(): array
    {
        return array_filter([
            'weight' => $this->weight,
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
            'declaredPrice' => $this->declaredPrice,
            'name' => $this->name,
            'description' => $this->description,
            'postPay' => $this->postPay,
            'parcelItems' => $this->parcelItems,
        ], static fn (mixed $value): bool => ! $value instanceof Optional);
    }
}
