<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels\Shipment;

use Illuminate\Support\Collection;
use Sashalenz\UkrPostApi\ApiModels\BaseModel;
use Sashalenz\UkrPostApi\ApiModels\RequestData\CreateShipmentRequest;
use Sashalenz\UkrPostApi\ApiModels\ResponseData\ShipmentData;
use Sashalenz\UkrPostApi\Endpoint;
use Sashalenz\UkrPostApi\Enums\DeliveryType;
use Sashalenz\UkrPostApi\Enums\ShipmentStatus;
use Sashalenz\UkrPostApi\Exceptions\UkrPostValidationException;
use Sashalenz\UkrPostApi\Request;
use Sashalenz\UkrPostApi\Support\ShipmentConstraints;
use Sashalenz\UkrPostApi\Support\ShipmentValidationContext;

final class Shipment extends BaseModel
{
    /** @param array<string, mixed>|CreateShipmentRequest $data */
    public function create(CreateShipmentRequest|array $data): ShipmentData
    {
        return $this->createOne('/shipments', $data);
    }

    /**
     * @param  CreateShipmentRequest|array<string, mixed>|list<CreateShipmentRequest|array<string, mixed>>  $data
     * @return ShipmentData|Collection<int, ShipmentData>
     */
    public function createInGroup(string $groupUuid, CreateShipmentRequest|array $data): ShipmentData|Collection
    {
        $path = '/shipment-groups/'.rawurlencode($groupUuid).'/shipments';
        if ($data instanceof CreateShipmentRequest) {
            return $this->createOne($path, $data);
        }

        if (! array_is_list($data)) {
            return $this->createOne($path, self::requiredObject($data, 'shipment'));
        }

        if ($data === []) {
            throw new UkrPostValidationException('At least one shipment is required.');
        }

        $payload = [];
        foreach ($data as $shipment) {
            $item = $shipment instanceof CreateShipmentRequest
                ? $shipment->toApiArray()
                : self::requiredObject($shipment, 'shipment');
            ShipmentConstraints::validate($item, $this->resolveValidationContext($item));
            $payload[] = $item;
        }

        $response = self::requiredObjectList($this->post($path, $payload)->all(), 'shipments');

        return collect(array_map(
            static fn (array $item): ShipmentData => ShipmentData::from($item),
            $response,
        ));
    }

    public function find(string $uuidOrBarcode): ShipmentData
    {
        return ShipmentData::from($this->get('/shipments/'.rawurlencode($uuidOrBarcode))->all());
    }

    public function findByBarcode(string $barcode): ShipmentData
    {
        return ShipmentData::from($this->get('/shipments/barcode/'.rawurlencode($barcode))->all());
    }

    /** @param array<string, mixed> $data */
    public function update(string $uuid, array $data): ShipmentData
    {
        $current = $this->ensureMutable($uuid);
        $merged = array_replace(self::stringKeyedArray($current->toArray()) ?? [], $data);
        ShipmentConstraints::validate($merged, $this->resolveValidationContext($merged));

        return ShipmentData::from($this->put('/shipments/'.rawurlencode($uuid), $data)->all());
    }

    /** @return Collection<int, mixed> */
    public function delete(string $uuid): Collection
    {
        $this->ensureMutable($uuid);

        return parent::delete('/shipments/'.rawurlencode($uuid));
    }

    /** @return Collection<int, mixed> */
    public function lifecycle(string $uuidOrBarcode): Collection
    {
        return $this->get('/shipments/'.rawurlencode($uuidOrBarcode).'/lifecycle');
    }

    /**
     * @param  array<int, array<string, mixed>>  $parcels
     * @return Collection<int, mixed>
     */
    public function addParcels(string $uuidOrBarcode, array $parcels): Collection
    {
        $current = $this->ensureMutable($uuidOrBarcode);
        $shipment = self::stringKeyedArray($current->toArray()) ?? [];
        $existing = $shipment['parcels'] ?? [];

        if (! is_array($existing)) {
            throw new UkrPostValidationException('Unexpected shipment parcels response.');
        }

        $shipment['parcels'] = [...$existing, ...$parcels];
        ShipmentConstraints::validate($shipment, $this->resolveValidationContext($shipment));

        return $this->post('/shipments/'.rawurlencode($uuidOrBarcode).'/parcels', $parcels);
    }

    /** @return Collection<int, mixed> */
    public function shipmentGroup(string $uuid): Collection
    {
        return $this->get('/shipments/shipment-group/'.rawurlencode($uuid));
    }

    /** @return Collection<int, mixed> */
    public function removeFromGroup(string $uuid): Collection
    {
        return parent::delete('/shipments/'.rawurlencode($uuid).'/shipment-group');
    }

    /** @return Collection<int, mixed> */
    public function isPriceChangedInPostOffice(string $barcode): Collection
    {
        return $this->get('/shipments/barcode/'.rawurlencode($barcode).'/isPriceChangedInPostOffice');
    }

    /** @return Collection<int, mixed> */
    public function priceChangesBySender(string $senderUuid, string $from, string $to): Collection
    {
        return $this->get('/shipments/sender/'.rawurlencode($senderUuid).'/from/'.rawurlencode($from).'/to/'.rawurlencode($to).'/isPriceChangedInPostOffice');
    }

    private function ensureMutable(string $uuidOrBarcode): ShipmentData
    {
        // A caller's saved status may be stale after the parcel has been accepted at a post office.
        $shipment = $this->find($uuidOrBarcode);

        if ($shipment->status !== ShipmentStatus::CREATED) {
            throw new UkrPostValidationException('Only CREATED shipments can be updated or deleted.');
        }

        return $shipment;
    }

    /** @param CreateShipmentRequest|array<string, mixed> $data */
    private function createOne(string $path, CreateShipmentRequest|array $data): ShipmentData
    {
        $payload = $data instanceof CreateShipmentRequest ? $data->toApiArray() : $data;
        ShipmentConstraints::validate($payload, $this->resolveValidationContext($payload));

        return ShipmentData::from($this->post($path, $payload)->all());
    }

    /** @param array<string, mixed> $payload */
    private function resolveValidationContext(array $payload): ShipmentValidationContext
    {
        $sender = self::arrayValue($payload['sender'] ?? null);
        $recipient = self::arrayValue($payload['recipient'] ?? null);
        $postPay = $payload['postPay'] ?? 0;
        $delivery = self::deliveryType($payload['deliveryType'] ?? null);
        $parcels = $payload['parcels'] ?? [];
        $needsMobileCheck = is_array($parcels) && count($parcels) > 5;

        if ((is_int($postPay) || is_float($postPay)) && $postPay > 0) {
            $sender = $this->resolveClient($sender, 'sender', true);
            $recipient = $this->resolveClient($recipient, 'recipient', true);
        }

        $address = self::arrayValue($recipient['address'] ?? null);
        if ($delivery?->hasCourierDelivery() || $needsMobileCheck) {
            $recipient = $this->resolveClient($recipient, 'recipient');
            $address = $this->resolveRecipientAddress($payload, $recipient, $address);
        }

        $mobile = false;
        if ($needsMobileCheck) {
            $postcode = $address['postcode'] ?? null;
            if (! is_string($postcode) || ! preg_match('/^\d{5}$/', $postcode)) {
                throw new UkrPostValidationException('Recipient postcode is required to validate a multi-parcel shipment.');
            }
            $mobile = $this->isMobilePostOffice($postcode);
        }

        return new ShipmentValidationContext($sender, $recipient, $address, $mobile);
    }

    /**
     * @param  array<string, mixed>|null  $client
     * @return array<string, mixed>
     */
    private function resolveClient(?array $client, string $role, bool $requireType = false): array
    {
        if ($client === null) {
            throw new UkrPostValidationException($role.' must identify a client.');
        }

        if (($requireType && isset($client['type'])) || (! $requireType && (isset($client['address']) || isset($client['addressId'])))) {
            return $client;
        }

        $uuid = $client['uuid'] ?? null;
        if (! is_string($uuid) || $uuid === '') {
            throw new UkrPostValidationException($role.' UUID is required.');
        }

        $resolved = self::stringKeyedArray($this->get('/clients/'.rawurlencode($uuid))->all());

        return $resolved ?? throw new UkrPostValidationException('Unexpected '.$role.' client response.');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $recipient
     * @param  array<string, mixed>|null  $address
     * @return array<string, mixed>
     */
    private function resolveRecipientAddress(array $payload, array $recipient, ?array $address): array
    {
        if ($address !== null) {
            return $address;
        }

        $addressId = $payload['recipientAddressId'] ?? $recipient['addressId'] ?? null;
        if ((! is_int($addressId) && ! is_string($addressId)) || $addressId === '') {
            throw new UkrPostValidationException('Recipient addressId is required for this delivery.');
        }

        $resolved = self::stringKeyedArray($this->get('/addresses/'.rawurlencode((string) $addressId))->all());

        return $resolved ?? throw new UkrPostValidationException('Unexpected recipient address response.');
    }

    private function isMobilePostOffice(string $postcode): bool
    {
        $ttl = config('ukrpost-api.cache_ttl', 3600);
        $result = (new Request(
            $this->credentials,
            Endpoint::CLASSIFIER,
            'GET',
            'get_postoffices_by_postindex',
            query: ['pi' => $postcode],
        ))->cache(is_int($ttl) ? $ttl : 3600);

        if (! $result instanceof Collection) {
            return false;
        }

        $entries = $result->get('Entries');
        if (! is_array($entries)) {
            return false;
        }

        $items = $entries['Entry'] ?? [];
        if (! is_array($items)) {
            return false;
        }

        if (isset($items['TYPE_ACRONYM'])) {
            $items = [$items];
        }

        foreach ($items as $item) {
            $office = self::stringKeyedArray($item);
            if ($office === null) {
                continue;
            }

            $typeLong = $office['TYPE_LONG'] ?? null;
            if (($office['TYPE_ACRONYM'] ?? null) === 'ПВ' || (is_string($typeLong) && str_contains($typeLong, 'Пересув'))) {
                return true;
            }
        }

        return false;
    }

    private static function deliveryType(mixed $value): ?DeliveryType
    {
        return $value instanceof DeliveryType ? $value : (is_string($value) ? DeliveryType::tryFrom($value) : null);
    }

    /** @return array<string, mixed>|null */
    private static function arrayValue(mixed $value): ?array
    {
        return self::stringKeyedArray($value);
    }

    /** @return array<string, mixed>|null */
    private static function stringKeyedArray(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $result = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                return null;
            }

            $result[$key] = $item;
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private static function requiredObject(mixed $value, string $context): array
    {
        return self::stringKeyedArray($value)
            ?? throw new UkrPostValidationException('Unexpected '.$context.' object.');
    }

    /**
     * @param  array<array-key, mixed>  $items
     * @return list<array<string, mixed>>
     */
    private static function requiredObjectList(array $items, string $context): array
    {
        $result = [];

        foreach ($items as $item) {
            $result[] = self::requiredObject($item, $context);
        }

        return $result;
    }
}
