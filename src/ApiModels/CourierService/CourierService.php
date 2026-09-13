<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels\CourierService;

use Illuminate\Support\Collection;
use Sashalenz\UkrPostApi\ApiModels\BaseModel;
use Sashalenz\UkrPostApi\ApiModels\RequestData\CourierOrderRequestData;
use Sashalenz\UkrPostApi\ApiModels\ResponseData\CourierOrderData;
use Sashalenz\UkrPostApi\ApiModels\ResponseData\CourierOrderStatusData;
use Sashalenz\UkrPostApi\Exceptions\UkrPostValidationException;

final class CourierService extends BaseModel
{
    /** @param CourierOrderRequestData|array<string, mixed> $data */
    public function createOrder(CourierOrderRequestData|array $data): CourierOrderData
    {
        $request = $data instanceof CourierOrderRequestData ? $data : CourierOrderRequestData::from($data);

        return CourierOrderData::from($this->post('/courier-service/orders', $request->toApiArray())->all());
    }

    public function order(string $orderUuid): CourierOrderData
    {
        return CourierOrderData::from($this->get('/courier-service/orders/'.rawurlencode($orderUuid))->all());
    }

    /** @return Collection<int, CourierOrderStatusData> */
    public function statuses(string $orderUuid): Collection
    {
        return collect(array_map(
            static fn (array $status): CourierOrderStatusData => CourierOrderStatusData::from($status),
            self::listOfObjects($this->get('/courier-service/orders/'.rawurlencode($orderUuid).'/statuses')->all(), 'courier order statuses'),
        ));
    }

    /** @return Collection<int, CourierOrderData> */
    public function ordersByClient(string $clientUuid): Collection
    {
        return collect(array_map(
            static fn (array $order): CourierOrderData => CourierOrderData::from($order),
            self::listOfObjects($this->get('/clients/'.rawurlencode($clientUuid).'/courier-delivery-orders')->all(), 'courier orders'),
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
}
