<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Sashalenz\UkrPostApi\ApiModels\RequestData\AddressRequestData;
use Sashalenz\UkrPostApi\ApiModels\RequestData\CreateShipmentRequest;
use Sashalenz\UkrPostApi\Credentials;
use Sashalenz\UkrPostApi\Enums\DeliveryType;
use Sashalenz\UkrPostApi\Enums\ShipmentType;
use Sashalenz\UkrPostApi\Exceptions\UkrPostValidationException;
use Sashalenz\UkrPostApi\UkrPostApi;

it('resolves recipient address before courier shipment creation', function (): void {
    Http::fake([
        '*/clients/recipient?*' => Http::response(['addressId' => 42]),
        '*/addresses/42?*' => Http::response(['street' => 'Хрещатик', 'houseNumber' => '1']),
        '*/shipments?*' => Http::response(['uuid' => 'created']),
    ]);
    UkrPostApi::shipments(new Credentials('bearer', 'token'))->create([
        'sender' => ['uuid' => 'sender'], 'recipient' => ['uuid' => 'recipient'],
        'deliveryType' => 'W2D', 'type' => 'STANDARD',
        'parcels' => [['weight' => 1000, 'length' => 30, 'width' => 20, 'height' => 10]],
    ]);
    Http::assertSentCount(3);
    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST');
});

it('never creates a courier shipment with an incomplete resolved address', function (): void {
    Http::fake(['*/clients/recipient?*' => Http::response(['addressId' => 42]), '*/addresses/42?*' => Http::response(['street' => 'Хрещатик'])]);
    expect(fn () => UkrPostApi::shipments(new Credentials('bearer', 'token'))->create([
        'sender' => ['uuid' => 'sender'], 'recipient' => ['uuid' => 'recipient'],
        'deliveryType' => 'W2D', 'type' => 'STANDARD',
        'parcels' => [['weight' => 1000, 'length' => 30, 'width' => 20, 'height' => 10]],
    ]))->toThrow(UkrPostValidationException::class);
    Http::assertNotSent(fn (ClientRequest $request): bool => $request->method() === 'POST');
});

it('rejects postPay for an individual without middleName', function (): void {
    Http::fake([
        '*/clients/sender?*' => Http::response(['type' => 'INDIVIDUAL', 'middleName' => null]),
        '*/clients/recipient?*' => Http::response(['type' => 'INDIVIDUAL']),
    ]);
    expect(fn () => UkrPostApi::shipments(new Credentials('bearer', 'token'))->create([
        'sender' => ['uuid' => 'sender'], 'recipient' => ['uuid' => 'recipient'],
        'postPay' => 100, 'deliveryType' => 'W2W', 'type' => 'STANDARD',
        'parcels' => [['weight' => 1000, 'length' => 30, 'width' => 20, 'height' => 10]],
    ]))->toThrow(UkrPostValidationException::class);
    Http::assertSentCount(2);
    Http::assertNotSent(fn (ClientRequest $request): bool => $request->method() === 'POST');
});

it('sends a single DELETE for a shipment', function (): void {
    Http::fake(['*' => Http::response(['status' => 'CREATED'], 200)]);

    UkrPostApi::shipments(new Credentials('bearer', 'token'))->delete('shipment-id');

    Http::assertSentCount(2);
    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'DELETE'
        && $request->url() === 'https://www.ukrposhta.ua/ecom/0.0.1/shipments/shipment-id?token=token');
});

it('rejects mutation after registration', function (string $operation): void {
    Http::fake(['*' => Http::response(['status' => 'REGISTERED'])]);
    $shipments = UkrPostApi::shipments(new Credentials('bearer', 'token'));
    expect(fn () => $operation === 'delete' ? $shipments->delete('shipment-id') : $shipments->update('shipment-id', ['description' => 'changed']))
        ->toThrow(UkrPostValidationException::class);
    Http::assertSentCount(1);
    Http::assertNotSent(fn (ClientRequest $request): bool => $request->method() !== 'GET');
})->with(['delete', 'update']);

it('validates the merged shipment before updating a created shipment', function (): void {
    Http::fake([
        '*/shipments/shipment-id?*' => Http::response([
            'uuid' => 'shipment-id',
            'status' => 'CREATED',
            'deliveryType' => 'W2W',
            'type' => 'STANDARD',
            'parcels' => [['weight' => 1000, 'length' => 30, 'width' => 20, 'height' => 10, 'declaredPrice' => 1]],
        ]),
        '*' => Http::response(['uuid' => 'shipment-id', 'status' => 'CREATED']),
    ]);

    $shipment = UkrPostApi::shipments(new Credentials('bearer', 'token'))->update('shipment-id', ['description' => 'Оновлено']);

    expect($shipment->uuid)->toBe('shipment-id');
    Http::assertSentCount(2);
    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'PUT');
});

it('removes group membership through the transport', function (): void {
    Http::fake(['*' => Http::response([], 200)]);

    UkrPostApi::shipments(new Credentials('bearer', 'token'))->removeFromGroup('shipment-id');

    Http::assertSentCount(1);
    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'DELETE'
        && $request->url() === 'https://www.ukrposhta.ua/ecom/0.0.1/shipments/shipment-id/shipment-group?token=token');
});

it('sends additional parcels as a JSON list', function (): void {
    Http::fake([
        '*/shipments/shipment-id?*' => Http::response([
            'status' => 'CREATED',
            'deliveryType' => 'W2W',
            'type' => 'STANDARD',
            'parcels' => [['weight' => 1000, 'length' => 30, 'width' => 20, 'height' => 10, 'declaredPrice' => 1]],
        ]),
        '*/shipments/shipment-id/parcels?*' => Http::response([], 200),
    ]);
    $parcels = [['weight' => 1000, 'length' => 30, 'width' => 20, 'height' => 10]];

    UkrPostApi::shipments(new Credentials('bearer', 'token'))->addParcels('shipment-id', $parcels);

    Http::assertSentCount(2);
    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
        && $request->data() === $parcels);
});

it('creates an address without a counterparty token', function (): void {
    Http::fake(fn (ClientRequest $request) => Http::response(['id' => 123]));
    UkrPostApi::addresses(new Credentials('bearer', 'token'))->create(new AddressRequestData('01001', 'Київська', 'Бучанський', 'Київ'));
    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST' && str_contains($request->url(), '/addresses') && ! str_contains($request->url(), 'token='));
});

it('creates a shipment with token and validates parcel constraints', function (): void {
    Http::fake(fn (ClientRequest $request) => Http::response(['uuid' => 'shipment']));
    UkrPostApi::shipments(new Credentials('bearer', 'token'))->create(new CreateShipmentRequest(
        senderUuid: 'sender', recipientUuid: 'recipient', deliveryType: DeliveryType::W2W, type: ShipmentType::STANDARD,
        parcels: [['weight' => 1000, 'length' => 30, 'width' => 20, 'height' => 10]],
    ));
    Http::assertSent(fn (ClientRequest $request): bool => str_contains($request->url(), '/shipments') && str_contains($request->url(), 'token=token'));
});

it('rejects more than five parcels for a mobile post office before creating a shipment', function (): void {
    Http::fake([
        '*/clients/recipient?*' => Http::response(['addressId' => 42]),
        '*/addresses/42?*' => Http::response(['postcode' => '52941']),
        '*get_postoffices_by_postindex*' => Http::response(['Entries' => ['Entry' => [['TYPE_ACRONYM' => 'ПВ']]]]),
    ]);

    $parcels = array_fill(0, 6, ['weight' => 1000, 'length' => 30, 'width' => 20, 'height' => 10]);
    $parcels[0]['declaredPrice'] = 1;

    expect(fn () => UkrPostApi::shipments(new Credentials('bearer', 'token'))->create([
        'sender' => ['uuid' => 'sender'],
        'recipient' => ['uuid' => 'recipient'],
        'deliveryType' => 'W2W',
        'type' => 'STANDARD',
        'parcels' => $parcels,
    ]))->toThrow(UkrPostValidationException::class);

    Http::assertSentCount(3);
    Http::assertNotSent(fn (ClientRequest $request): bool => $request->method() === 'POST');
});

it('rejects invalid shipment dimensions before HTTP', function (): void {
    expect(fn () => UkrPostApi::shipments(new Credentials('bearer', 'token'))->create([
        'sender' => ['uuid' => 'sender'], 'recipient' => ['uuid' => 'recipient'], 'deliveryType' => 'W2W', 'type' => 'STANDARD',
        'parcels' => [['weight' => 1000, 'length' => 30, 'width' => 0, 'height' => 10]],
    ]))->toThrow(UkrPostValidationException::class);
});
