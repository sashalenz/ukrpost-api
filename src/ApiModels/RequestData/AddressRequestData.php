<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels\RequestData;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class AddressRequestData extends Data
{
    public function __construct(
        public string $postcode,
        public string $region,
        public string $district,
        public string $city,
        public ?string $country = 'UA',
        public string|null|Optional $street = new Optional,
        public string|null|Optional $houseNumber = new Optional,
        public string|null|Optional $apartmentNumber = new Optional,
        public string|null|Optional $mailbox = new Optional,
        public string|null|Optional $specialDestination = new Optional,
        public bool $lift = false,
        public int $floor = 0,
        public string|null|Optional $description = new Optional,
        public bool|null|Optional $countryside = new Optional,
        public string|null|Optional $ddCode = new Optional,
    ) {}

    /** @return array<string, mixed> */
    public function toApiArray(): array
    {
        return array_filter(['postcode' => $this->postcode, 'region' => $this->region, 'district' => $this->district, 'city' => $this->city, 'country' => $this->country, 'street' => $this->street, 'houseNumber' => $this->houseNumber, 'apartmentNumber' => $this->apartmentNumber, 'mailbox' => $this->mailbox, 'specialDestination' => $this->specialDestination, 'lift' => $this->lift, 'floor' => $this->floor, 'description' => $this->description, 'countryside' => $this->countryside, 'ddCode' => $this->ddCode], static fn (mixed $value): bool => ! $value instanceof Optional);
    }
}
