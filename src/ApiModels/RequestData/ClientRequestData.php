<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels\RequestData;

use Sashalenz\UkrPostApi\Enums\ClientType;
use Sashalenz\UkrPostApi\Enums\PostPayPaymentType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class ClientRequestData extends Data
{
    public function __construct(
        public ClientType|null|Optional $type = new Optional,
        public string|null|Optional $name = new Optional,
        public string|null|Optional $firstName = new Optional,
        public string|null|Optional $lastName = new Optional,
        public string|null|Optional $middleName = new Optional,
        public string|null|Optional $externalId = new Optional,
        public string|null|Optional $uniqueRegistrationNumber = new Optional,
        public string|null|Optional $edrpou = new Optional,
        public string|null|Optional $tin = new Optional,
        public bool $resident = true,
        public string|null|Optional $phoneNumber = new Optional,
        public string|null|Optional $email = new Optional,
        public string|null|Optional $contactPersonName = new Optional,
        public int|null|Optional $addressId = new Optional,
        public string|null|Optional $bankAccount = new Optional,
        public PostPayPaymentType|null|Optional $postPayPaymentType = new Optional,
        public bool|null|Optional $personalDataApproved = new Optional,
    ) {}

    /** @return array<string, mixed> */
    public function toApiArray(): array
    {
        return array_filter(['type' => $this->type instanceof ClientType ? $this->type->value : $this->type, 'name' => $this->name, 'firstName' => $this->firstName, 'lastName' => $this->lastName, 'middleName' => $this->middleName, 'externalId' => $this->externalId, 'uniqueRegistrationNumber' => $this->uniqueRegistrationNumber, 'edrpou' => $this->edrpou, 'tin' => $this->tin, 'resident' => $this->resident, 'phoneNumber' => $this->phoneNumber, 'email' => $this->email, 'contactPersonName' => $this->contactPersonName, 'addressId' => $this->addressId, 'bankAccount' => $this->bankAccount, 'postPayPaymentType' => $this->postPayPaymentType instanceof PostPayPaymentType ? $this->postPayPaymentType->value : $this->postPayPaymentType, 'personalDataApproved' => $this->personalDataApproved], static fn (mixed $value): bool => ! $value instanceof Optional);
    }
}
