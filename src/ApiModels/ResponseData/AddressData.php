<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels\ResponseData;

use Spatie\LaravelData\Data;

final class AddressData extends Data
{
    public function __construct(
        public ?int $id = null,
        public ?string $postcode = null,
        public ?string $country = null,
        public ?string $region = null,
        public ?string $district = null,
        public ?string $city = null,
        public ?string $street = null,
        public ?string $houseNumber = null,
        public ?string $apartmentNumber = null,
        public ?string $mailbox = null,
        public ?string $specialDestination = null,
        public ?bool $lift = null,
        public ?int $floor = null,
        public ?string $description = null,
        public ?bool $countryside = null,
        public ?string $ddCode = null,
        public ?string $foreignStreetHouseApartment = null,
        public ?string $detailedInfo = null,
        public ?string $created = null,
        public ?string $lastModified = null,
    ) {}
}
