<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels\Client;

use Illuminate\Support\Collection;
use Sashalenz\UkrPostApi\ApiModels\BaseModel;
use Sashalenz\UkrPostApi\ApiModels\RequestData\ClientRequestData;
use Sashalenz\UkrPostApi\ApiModels\ResponseData\ClientData;
use Sashalenz\UkrPostApi\Exceptions\UkrPostNotFoundException;
use Sashalenz\UkrPostApi\Exceptions\UkrPostValidationException;
use Sashalenz\UkrPostApi\Support\ClientConstraints;

final class Client extends BaseModel
{
    /**
     * @param  array<string, mixed>|ClientRequestData  $data
     */
    public function ensure(ClientRequestData|array $data): ClientData
    {
        $payload = $data instanceof ClientRequestData ? $data->toApiArray() : $data;
        $externalId = $payload['externalId'] ?? null;
        $phone = $payload['phoneNumber'] ?? null;
        if (is_string($externalId) && trim($externalId) !== '') {
            try {
                return $this->findByExternalId($externalId);
            } catch (UkrPostNotFoundException) {
                // Only a confirmed absence allows creation; connection and authentication failures must propagate.
            }
        } elseif (is_string($phone) && trim($phone) !== '') {
            $matches = $this->findByPhone($phone);
            if ($matches->count() > 1) {
                throw new UkrPostValidationException('Multiple clients share this phone; use externalId.');
            }
            if ($matches->isNotEmpty()) {
                $match = $matches->first();
                if (! is_array($match) || ! is_string($match['uuid'] ?? null)) {
                    throw new UkrPostValidationException('Unexpected client phone search response.');
                }

                return $this->find($match['uuid']);
            }
        } else {
            throw new UkrPostValidationException('ensure requires externalId or phoneNumber.');
        }

        return $this->create($payload);
    }

    /**
     * @param  array<string, mixed>|ClientRequestData  $data
     */
    public function create(ClientRequestData|array $data): ClientData
    {
        $payload = $data instanceof ClientRequestData ? $data->toApiArray() : $data;
        ClientConstraints::validateForCreate($payload);

        return ClientData::from($this->post('/clients', $payload)->all());
    }

    public function find(string $uuid): ClientData
    {
        return ClientData::from($this->get('/clients/'.rawurlencode($uuid))->all());
    }

    /**
     * @param  array<string, mixed>|ClientRequestData  $data
     */
    public function update(string $uuid, ClientRequestData|array $data): ClientData
    {
        $payload = $data instanceof ClientRequestData ? $data->toApiArray() : $data;
        ClientConstraints::validateForUpdate($payload);

        return ClientData::from($this->put('/clients/'.rawurlencode($uuid), $payload)->all());
    }

    public function findByExternalId(string $externalId): ClientData
    {
        return ClientData::from($this->get('/clients/external-id/'.rawurlencode($externalId))->all());
    }

    /** @return Collection<int, mixed> */
    public function findByPhone(string $phoneNumber, string $countryIso3166 = 'UA'): Collection
    {
        return $this->get('/clients/phone', ['countryISO3166' => $countryIso3166, 'phoneNumber' => $phoneNumber]);
    }

    /** @return Collection<int, mixed> */
    public function addresses(string $clientUuid): Collection
    {
        return $this->get('/client-addresses', ['clientUuid' => $clientUuid]);
    }

    /** @return Collection<int, mixed> */
    public function phones(string $clientUuid): Collection
    {
        return $this->get('/client-phones', ['clientUuid' => $clientUuid]);
    }

    /** @return Collection<int, mixed> */
    public function postPayRecipients(string $clientUuid): Collection
    {
        return $this->get('/clients/'.rawurlencode($clientUuid).'/post-pay-recipients');
    }

    /** @return Collection<int, mixed> */
    public function addPostPayRecipient(string $clientUuid, string $recipientUuid): Collection
    {
        return $this->post('/clients/'.rawurlencode($clientUuid).'/post-pay-recipients/'.rawurlencode($recipientUuid));
    }

    /** @return Collection<int, mixed> */
    public function removePostPayRecipient(string $clientUuid, string $recipientUuid): Collection
    {
        return $this->delete('/clients/'.rawurlencode($clientUuid).'/post-pay-recipients/'.rawurlencode($recipientUuid));
    }
}
