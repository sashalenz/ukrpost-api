<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels\Address;

use Illuminate\Support\Collection;
use Sashalenz\UkrPostApi\ApiModels\BaseModel;
use Sashalenz\UkrPostApi\ApiModels\RequestData\AddressRequestData;
use Sashalenz\UkrPostApi\ApiModels\ResponseData\AddressData;
use Sashalenz\UkrPostApi\Support\AddressConstraints;

final class Address extends BaseModel
{
    /**
     * @param  array<string, mixed>|AddressRequestData  $data
     */
    public function create(AddressRequestData|array $data): AddressData
    {
        $payload = $data instanceof AddressRequestData ? $data->toApiArray() : $data;
        AddressConstraints::validate($payload);

        return AddressData::from($this->post('/addresses', $payload)->all());
    }

    public function find(int|string $id): AddressData
    {
        return AddressData::from($this->get('/addresses/'.$id)->all());
    }

    /**
     * @param  array<string, mixed>|AddressRequestData  $data
     */
    public function update(int|string $id, AddressRequestData|array $data): AddressData
    {
        $payload = $data instanceof AddressRequestData ? $data->toApiArray() : $data;
        AddressConstraints::validate($payload, false);

        return AddressData::from($this->put('/addresses/'.$id, $payload)->all());
    }

    /** @return Collection<int, mixed> */
    public function availabilityChecking(string $senderPostcode, string $recipientPostcode): Collection
    {
        return $this->get('/addresses/availability-checking/from/'.$senderPostcode.'/to/'.$recipientPostcode);
    }
}
