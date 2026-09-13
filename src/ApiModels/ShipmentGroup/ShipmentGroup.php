<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels\ShipmentGroup;

use Illuminate\Support\Collection;
use Sashalenz\UkrPostApi\ApiModels\BaseModel;
use Sashalenz\UkrPostApi\ApiModels\RequestData\CreateShipmentRequest;
use Sashalenz\UkrPostApi\ApiModels\RequestData\ShipmentGroupRequestData;
use Sashalenz\UkrPostApi\ApiModels\ResponseData\ShipmentData;
use Sashalenz\UkrPostApi\ApiModels\ResponseData\ShipmentGroupData;
use Sashalenz\UkrPostApi\ApiModels\Shipment\Shipment;
use Sashalenz\UkrPostApi\Enums\GroupType;
use Sashalenz\UkrPostApi\Exceptions\UkrPostValidationException;

final class ShipmentGroup extends BaseModel
{
    /**
     * @param  ShipmentGroupRequestData|array<string, mixed>|list<ShipmentGroupRequestData|array<string, mixed>>  $data
     * @return ShipmentGroupData|Collection<int, ShipmentGroupData>
     */
    public function create(ShipmentGroupRequestData|array $data): ShipmentGroupData|Collection
    {
        if ($data instanceof ShipmentGroupRequestData) {
            return ShipmentGroupData::from($this->post('/shipment-groups', self::groupPayload($data))->all());
        }

        if (! array_is_list($data)) {
            return ShipmentGroupData::from($this->post('/shipment-groups', self::groupPayload(
                self::requiredGroupObject($data),
            ))->all());
        }

        if ($data === [] || count($data) > 100) {
            throw new UkrPostValidationException('A group batch must contain between 1 and 100 groups.');
        }

        $payload = [];
        foreach ($data as $group) {
            $payload[] = self::groupPayload(
                $group instanceof ShipmentGroupRequestData ? $group : self::requiredGroupObject($group),
            );
        }

        return self::groupsFrom($this->post('/shipment-groups', $payload)->all());
    }

    public function find(string $uuid): ShipmentGroupData
    {
        return ShipmentGroupData::from($this->get('/shipment-groups/'.rawurlencode($uuid))->all());
    }

    /** @param array<string, mixed> $data */
    public function update(string $uuid, array $data): ShipmentGroupData
    {
        $this->ensureOpen($uuid);
        $payload = self::normalizeGroupPayload($data, false);

        return ShipmentGroupData::from($this->put('/shipment-groups/'.rawurlencode($uuid), $payload)->all());
    }

    /** @return Collection<int, ShipmentData> */
    public function shipments(string $uuid): Collection
    {
        $items = $this->get('/shipment-groups/'.rawurlencode($uuid).'/shipments')->all();

        return collect(array_map(
            static fn (array $item): ShipmentData => ShipmentData::from($item),
            self::listOfObjects($items, 'shipment group shipments'),
        ));
    }

    /** @return Collection<int, mixed> */
    public function addShipment(string $groupUuid, string $shipmentUuid): Collection
    {
        $this->ensureOpen($groupUuid);

        return $this->post('/shipment-groups/'.rawurlencode($groupUuid).'/shipments/'.rawurlencode($shipmentUuid));
    }

    /**
     * @param  list<string>  $shipmentUuidsOrBarcodes
     * @return Collection<int, mixed>
     */
    public function addShipments(string $groupUuid, array $shipmentUuidsOrBarcodes): Collection
    {
        if ($shipmentUuidsOrBarcodes === [] || count($shipmentUuidsOrBarcodes) > 500) {
            throw new UkrPostValidationException('A group assignment must contain between 1 and 500 shipments.');
        }

        $this->ensureOpen($groupUuid);

        return $this->put('/shipment-groups/'.rawurlencode($groupUuid).'/shipments', $shipmentUuidsOrBarcodes);
    }

    /**
     * @param  CreateShipmentRequest|array<string, mixed>|list<CreateShipmentRequest|array<string, mixed>>  $data
     * @return ShipmentData|Collection<int, ShipmentData>
     */
    public function createShipmentInGroup(string $groupUuid, CreateShipmentRequest|array $data): ShipmentData|Collection
    {
        $this->ensureOpen($groupUuid);

        return Shipment::make($this->credentials)->createInGroup($groupUuid, $data);
    }

    /** @return Collection<int, mixed> */
    public function removeShipment(string $groupUuid, string $shipmentUuid): Collection
    {
        $this->ensureOpen($groupUuid);

        return $this->delete('/shipments/'.rawurlencode($shipmentUuid).'/shipment-group');
    }

    public function count(string $uuid): int
    {
        $quantity = $this->get('/shipment-groups/'.rawurlencode($uuid).'/shipments-count')->get('quantity');

        if (! is_int($quantity)) {
            throw new UkrPostValidationException('Unexpected shipment group count response.');
        }

        return $quantity;
    }

    /** @return Collection<int, ShipmentGroupData> */
    public function byClient(string $clientUuid, ?GroupType $type = null): Collection
    {
        $query = $type === null ? [] : ['type' => $type->value];
        $response = $this->get('/shipment-groups/clients/'.rawurlencode($clientUuid), $query)->all();

        if (! array_is_list($response)) {
            $response = [$response];
        }

        return self::groupsFrom($response);
    }

    private function ensureOpen(string $uuid): void
    {
        // Group state can change at registration, so mutation must use a fresh server response.
        if ($this->find($uuid)->closed === true) {
            throw new UkrPostValidationException('A closed shipment group cannot be changed.');
        }
    }

    /** @param ShipmentGroupRequestData|array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function groupPayload(ShipmentGroupRequestData|array $data): array
    {
        return $data instanceof ShipmentGroupRequestData
            ? self::normalizeGroupPayload($data->toApiArray())
            : self::normalizeGroupPayload($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function normalizeGroupPayload(array $data, bool $requireName = true): array
    {
        $name = $data['name'] ?? null;
        if ($requireName && (! is_string($name) || trim($name) === '')) {
            throw new UkrPostValidationException('Shipment group name is required.');
        }

        if (($data['type'] ?? null) instanceof GroupType) {
            $data['type'] = $data['type']->value;
        }

        return $data;
    }

    /**
     * @param  array<array-key, mixed>  $items
     * @return Collection<int, ShipmentGroupData>
     */
    private static function groupsFrom(array $items): Collection
    {
        return collect(array_map(
            static fn (array $item): ShipmentGroupData => ShipmentGroupData::from($item),
            self::listOfObjects($items, 'shipment groups'),
        ));
    }

    /**
     * @param  array<array-key, mixed>  $items
     * @return list<array<string, mixed>>
     */
    private static function listOfObjects(array $items, string $context): array
    {
        $result = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                throw new UkrPostValidationException('Unexpected '.$context.' response.');
            }

            /** @var array<string, mixed> $item */
            $result[] = $item;
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private static function requiredGroupObject(mixed $value): array
    {
        if (! is_array($value)) {
            throw new UkrPostValidationException('Unexpected shipment group object.');
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw new UkrPostValidationException('Unexpected shipment group object.');
            }

            $result[$key] = $item;
        }

        return $result;
    }
}
