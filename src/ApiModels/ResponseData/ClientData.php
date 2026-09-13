<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels\ResponseData;

use Sashalenz\UkrPostApi\Enums\ClientType;
use Sashalenz\UkrPostApi\Enums\PostPayPaymentType;
use Spatie\LaravelData\Data;

final class ClientData extends Data
{
    public function __construct(
        public ?string $uuid = null,
        public ?ClientType $type = null,
        public ?string $name = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $middleName = null,
        public ?string $externalId = null,
        public ?string $phoneNumber = null,
        public ?string $email = null,
        public ?int $addressId = null,
        public ?string $tin = null,
        public ?string $edrpou = null,
        public ?string $bankAccount = null,
        public ?PostPayPaymentType $postPayPaymentType = null,
    ) {}
}
