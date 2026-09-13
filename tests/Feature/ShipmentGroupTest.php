<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Sashalenz\UkrPostApi\ApiModels\ResponseData\ShipmentData;
use Sashalenz\UkrPostApi\ApiModels\ResponseData\ShipmentGroupData;
use Sashalenz\UkrPostApi\Credentials;
use Sashalenz\UkrPostApi\Enums\GroupType;
use Sashalenz\UkrPostApi\Exceptions\UkrPostValidationException;
use Sashalenz\UkrPostApi\UkrPostApi;

it('creates one shipment group and maps its response', function (): void {
    Http::fake(['*' => Http::response([
        'uuid' => 'group-id',
        'name' => 'Вересень',
        'type' => 'STANDARD',
        'clientUuid' => 'client-id',
        'barcode_g_id' => '10102024214851501',
        'closed' => false,
    ])]);

    $group = UkrPostApi::groups(new Credentials('bearer', 'token'))->create([
        'name' => 'Вересень',
        'clientUuid' => 'client-id',
        'type' => GroupType::STANDARD,
    ]);

    expect($group)->toBeInstanceOf(ShipmentGroupData::class)
        ->and($group->type)->toBe(GroupType::STANDARD)
        ->and($group->barcodeGId)->toBe('10102024214851501');
    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://www.ukrposhta.ua/ecom/0.0.1/shipment-groups?token=token'
        && $request->data()['type'] === 'STANDARD');
});

it('creates shipment groups in one request', function (): void {
    Http::fake(['*' => Http::response([
        ['uuid' => 'one', 'name' => 'One', 'type' => 'EXPRESS'],
        ['uuid' => 'two', 'name' => 'Two', 'type' => 'DOCUMENT'],
    ])]);

    $groups = UkrPostApi::groups(new Credentials('bearer', 'token'))->create([
        ['name' => 'One'],
        ['name' => 'Two', 'type' => GroupType::DOCUMENT],
    ]);

    expect($groups)->toBeInstanceOf(Collection::class)
        ->and($groups)->toHaveCount(2)
        ->and($groups->last())->toBeInstanceOf(ShipmentGroupData::class);
    Http::assertSentCount(1);
});

it('rejects invalid group batches before HTTP', function (array $groups): void {
    Http::fake();

    expect(fn () => UkrPostApi::groups(new Credentials('bearer', 'token'))->create($groups))
        ->toThrow(UkrPostValidationException::class);
    Http::assertNothingSent();
})->with([
    'empty' => [[]],
    'over one hundred' => [array_fill(0, 101, ['name' => 'Group'])],
]);

it('blocks mutation of a closed group', function (string $operation): void {
    Http::fake(['*' => Http::response([
        'uuid' => 'group-id', 'name' => 'Closed', 'type' => 'EXPRESS', 'closed' => true,
    ])]);
    $groups = UkrPostApi::groups(new Credentials('bearer', 'token'));

    expect(fn () => match ($operation) {
        'update' => $groups->update('group-id', ['name' => 'Changed']),
        'add' => $groups->addShipment('group-id', 'shipment-id'),
        'remove' => $groups->removeShipment('group-id', 'shipment-id'),
    })->toThrow(UkrPostValidationException::class);

    Http::assertSentCount(1);
    Http::assertNotSent(fn (ClientRequest $request): bool => $request->method() !== 'GET');
})->with(['update', 'add', 'remove']);

it('lists group shipments and returns their count', function (): void {
    Http::fakeSequence()
        ->push([['uuid' => 'shipment-id', 'status' => 'CREATED']])
        ->push(['message' => 'Quantity', 'quantity' => 1]);

    $groups = UkrPostApi::groups(new Credentials('bearer', 'token'));

    expect($groups->shipments('group-id'))->toHaveCount(1)
        ->and($groups->count('group-id'))->toBe(1);
});

it('rejects more than five hundred assignments before HTTP', function (): void {
    Http::fake();

    expect(fn () => UkrPostApi::groups(new Credentials('bearer', 'token'))->addShipments(
        'group-id',
        array_fill(0, 501, 'shipment-id'),
    ))->toThrow(UkrPostValidationException::class);

    Http::assertNothingSent();
});

it('mutates an open group through documented endpoints', function (string $operation, string $method, string $path): void {
    Http::fakeSequence()
        ->push(['uuid' => 'group-id', 'name' => 'Open', 'type' => 'EXPRESS', 'closed' => false])
        ->push($operation === 'update'
            ? ['uuid' => 'group-id', 'name' => 'Changed', 'type' => 'EXPRESS', 'closed' => false]
            : ['message' => 'ok']);

    $groups = UkrPostApi::groups(new Credentials('bearer', 'token'));
    match ($operation) {
        'update' => $groups->update('group-id', ['name' => 'Changed']),
        'add' => $groups->addShipment('group-id', 'shipment-id'),
        'remove' => $groups->removeShipment('group-id', 'shipment-id'),
    };

    Http::assertSentCount(2);
    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === $method
        && str_contains($request->url(), $path));
})->with([
    'update' => ['update', 'PUT', '/shipment-groups/group-id?'],
    'add' => ['add', 'POST', '/shipment-groups/group-id/shipments/shipment-id?'],
    'remove' => ['remove', 'DELETE', '/shipments/shipment-id/shipment-group?'],
]);

it('creates a validated shipment directly in an open group', function (): void {
    Http::fakeSequence()
        ->push(['uuid' => 'group-id', 'name' => 'Open', 'type' => 'STANDARD', 'closed' => false])
        ->push(['uuid' => 'shipment-id', 'status' => 'CREATED']);

    $shipment = UkrPostApi::groups(new Credentials('bearer', 'token'))->createShipmentInGroup('group-id', [
        'sender' => ['uuid' => 'sender'],
        'recipient' => ['uuid' => 'recipient'],
        'deliveryType' => 'W2W',
        'type' => 'STANDARD',
        'parcels' => [['weight' => 1000, 'length' => 30, 'width' => 20, 'height' => 10]],
    ]);

    expect($shipment)->toBeInstanceOf(ShipmentData::class);
    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
        && str_contains($request->url(), '/shipment-groups/group-id/shipments?'));
});

it('filters client groups by typed group type', function (): void {
    Http::fake(['*' => Http::response([
        ['uuid' => 'group-id', 'name' => 'Standard', 'type' => 'STANDARD', 'closed' => false],
    ])]);

    $groups = UkrPostApi::groups(new Credentials('bearer', 'token'))->byClient('client-id', GroupType::STANDARD);

    expect($groups)->toHaveCount(1);
    Http::assertSent(fn (ClientRequest $request): bool => str_contains(
        $request->url(),
        '/shipment-groups/clients/client-id?type=STANDARD&token=token',
    ));
});
