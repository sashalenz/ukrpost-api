<?php

declare(strict_types=1);

use Sashalenz\UkrPostApi\ApiModels\RequestData\AddressRequestData;
use Sashalenz\UkrPostApi\ApiModels\RequestData\CreateShipmentRequest;
use Sashalenz\UkrPostApi\ApiModels\RequestData\ParcelRequestData;
use Sashalenz\UkrPostApi\ApiModels\ResponseData\ParcelData;
use Sashalenz\UkrPostApi\ApiModels\ResponseData\ParcelItemData;
use Sashalenz\UkrPostApi\ApiModels\ResponseData\ShipmentData;
use Sashalenz\UkrPostApi\Enums\DeliveryType;

it('distinguishes omitted address fields from explicit null', function (): void {
    $data = new AddressRequestData('01001', 'Київська', 'Київський', 'Київ');
    expect($data->toApiArray())->not->toHaveKey('street');
    $data->street = null;
    expect($data->toApiArray())->toHaveKey('street', null);
});

it('serializes the canonical shipment request and nested parcel DTOs', function (): void {
    $request = new CreateShipmentRequest(
        senderUuid: 'sender',
        recipientUuid: 'recipient',
        deliveryType: DeliveryType::W2W,
        parcels: [new ParcelRequestData(1000, 30, 20, 10)],
    );

    expect($request->toApiArray())
        ->toHaveKey('sender.uuid', 'sender')
        ->toHaveKey('recipient.uuid', 'recipient')
        ->toHaveKey('parcels.0.width', 20)
        ->not->toHaveKey('postPay');
});

it('hydrates the delivery type actually returned by the API and nested parcels', function (): void {
    $data = ShipmentData::from(['uuid' => 'saved', 'deliveryType' => 'W2D', 'parcels' => [[
        'weight' => 1000,
        'declaredPrice' => 300.0,
        'parcelItems' => [['name' => 'Документ', 'value' => 300.0, 'quantity' => 1]],
    ]]]);
    expect($data->deliveryType)->toBe(DeliveryType::W2D)
        ->and($data->parcels[0])->toBeInstanceOf(ParcelData::class)
        ->and($data->parcels[0]->weight)->toBe(1000)
        ->and($data->parcels[0]->parcelItems[0])->toBeInstanceOf(ParcelItemData::class);
});
