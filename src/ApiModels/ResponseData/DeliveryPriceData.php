<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels\ResponseData;

use Spatie\LaravelData\Data;

final class DeliveryPriceData extends Data
{
    /**
     * @param  list<array<string, mixed>>  $discounts
     * @param  list<array<string, mixed>>  $parcels
     */
    public function __construct(
        public ?float $deliveryPrice = null,
        public ?float $rawDeliveryPrice = null,
        public ?float $postPayDeliveryPrice = null,
        public ?float $returnDeliveryPrice = null,
        public ?float $declaredPriceSurcharge = null,
        public ?string $calculationDescription = null,
        public array $discounts = [],
        public array $parcels = [],
        public ?bool $validate = null,
        public ?bool $recalculation = null,
    ) {}
}
