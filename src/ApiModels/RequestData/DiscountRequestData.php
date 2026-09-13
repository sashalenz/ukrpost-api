<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels\RequestData;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class DiscountRequestData extends Data
{
    public function __construct(
        public string|null|Optional $description,
        public float $rate,
    ) {}

    /** @return array<string, mixed> */
    public function toApiArray(): array
    {
        return array_filter([
            'description' => $this->description,
            'rate' => $this->rate,
        ], static fn (mixed $value): bool => ! $value instanceof Optional);
    }
}
