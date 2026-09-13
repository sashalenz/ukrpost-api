<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Sashalenz\UkrPostApi\ApiModels\RequestData\CourierOrderRequestData;
use Sashalenz\UkrPostApi\ApiModels\ResponseData\CourierOrderData;
use Sashalenz\UkrPostApi\ApiModels\ResponseData\CourierOrderStatusData;
use Sashalenz\UkrPostApi\Credentials;
use Sashalenz\UkrPostApi\Enums\CourierInterval;
use Sashalenz\UkrPostApi\Enums\CourierOrderStatus;
use Sashalenz\UkrPostApi\Enums\CourierOrderType;
use Sashalenz\UkrPostApi\Exceptions\UkrPostValidationException;
use Sashalenz\UkrPostApi\UkrPostApi;

/** @return array<string, mixed> */
function courierOrderResponse(array $overrides = []): array
{
    return array_replace([
        'uuid' => 'order-id',
        'orderNumber' => 3219085,
        'clientUuid' => 'client-id',
        'type' => 'SINGLE',
        'addressId' => 1357331,
        'phoneId' => 387865,
        'email' => null,
        'dropDate' => '2026-09-14',
        'interval' => 'INTERVAL_09_12',
        'shipmentBarcodes' => ['0500101983180'],
        'letterBarcodes' => [],
        'lastStatus' => 'ORDERED',
        'lastStatusDate' => '2026-09-13T11:31:05',
    ], $overrides);
}

function singleCourierOrder(): CourierOrderRequestData
{
    return new CourierOrderRequestData(
        clientUuid: 'client-id',
        type: CourierOrderType::SINGLE,
        addressId: 1357331,
        phoneId: 387865,
        dropDate: '2026-09-14',
        interval: CourierInterval::INTERVAL_09_12,
        shipmentBarcodes: ['0500101983180'],
    );
}

it('creates a typed courier order through the documented endpoint', function (): void {
    Http::fake(['*' => Http::response(courierOrderResponse())]);

    $order = UkrPostApi::courier(new Credentials('bearer', 'token'))->createOrder(singleCourierOrder());

    expect($order)
        ->toBeInstanceOf(CourierOrderData::class)
        ->type->toBe(CourierOrderType::SINGLE)
        ->interval->toBe(CourierInterval::INTERVAL_09_12)
        ->lastStatus->toBe(CourierOrderStatus::ORDERED);
    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
        && str_contains($request->url(), '/courier-service/orders?token=token')
        && $request->data()['type'] === 'SINGLE');
});

it('loads an order, its statuses, and client orders as typed data', function (string $method): void {
    Http::fake([
        '*/courier-service/orders/order-id/statuses?*' => Http::response([[
            'uuid' => 'status-id',
            'orderUuid' => 'order-id',
            'statusDate' => '2026-09-13T11:31:05',
            'orderStatus' => 'TRANSFERRED_TO_COURIER',
        ]]),
        '*/courier-service/orders/order-id?*' => Http::response(courierOrderResponse()),
        '*/clients/client-id/courier-delivery-orders?*' => Http::response([courierOrderResponse()]),
    ]);
    $service = UkrPostApi::courier(new Credentials('bearer', 'token'));

    $result = match ($method) {
        'order' => $service->order('order-id'),
        'statuses' => $service->statuses('order-id')->first(),
        'client orders' => $service->ordersByClient('client-id')->first(),
    };

    expect($result)->toBeInstanceOf($method === 'statuses' ? CourierOrderStatusData::class : CourierOrderData::class);
    if ($result instanceof CourierOrderStatusData) {
        expect($result->orderStatus)->toBe(CourierOrderStatus::TRANSFERRED_TO_COURIER);
    }
})->with(['order', 'statuses', 'client orders']);

it('serializes every courier order enum without magic strings', function (): void {
    $payload = singleCourierOrder()->toApiArray();

    expect($payload)
        ->type->toBe(CourierOrderType::SINGLE->value)
        ->interval->toBe(CourierInterval::INTERVAL_09_12->value);
});

it('rejects invalid courier orders before sending HTTP', function (CourierOrderRequestData $request): void {
    Http::fake();

    expect(fn () => UkrPostApi::courier(new Credentials('bearer', 'token!?'))->createOrder($request))
        ->toThrow(UkrPostValidationException::class);
    Http::assertNothingSent();
})->with([
    'no barcode' => fn (): CourierOrderRequestData => new CourierOrderRequestData(
        'client-id', CourierOrderType::SINGLE, 1, 1, '2026-09-14', CourierInterval::INTERVAL_09_12,
    ),
    'single with two barcodes' => fn (): CourierOrderRequestData => new CourierOrderRequestData(
        'client-id', CourierOrderType::SINGLE, 1, 1, '2026-09-14', CourierInterval::INTERVAL_09_12, ['1', '2'],
    ),
    'mass below ten' => fn (): CourierOrderRequestData => new CourierOrderRequestData(
        'client-id', CourierOrderType::MASS, 1, 1, '2026-09-14', CourierInterval::INTERVAL_09_12, array_fill(0, 9, 'barcode'),
    ),
    'invalid date' => fn (): CourierOrderRequestData => new CourierOrderRequestData(
        'client-id', CourierOrderType::SINGLE, 1, 1, '2026-02-30', CourierInterval::INTERVAL_09_12, ['barcode'],
    ),
    'invalid identifiers' => fn (): CourierOrderRequestData => new CourierOrderRequestData(
        '', CourierOrderType::SINGLE, 0, 0, '2026-09-14', CourierInterval::INTERVAL_09_12, ['barcode'],
    ),
]);

it('accepts a mass order with ten barcodes', function (): void {
    Http::fake(['*' => Http::response(courierOrderResponse(['type' => 'MASS']))]);
    $request = new CourierOrderRequestData(
        'client-id',
        CourierOrderType::MASS,
        1,
        1,
        '2026-09-14',
        CourierInterval::INTERVAL_15_18,
        array_map(static fn (int $number): string => (string) $number, range(1, 10)),
    );

    expect(UkrPostApi::courier(new Credentials('bearer', 'token'))->createOrder($request)->type)
        ->toBe(CourierOrderType::MASS);
});
