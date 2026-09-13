<?php

declare(strict_types=1);

use Sashalenz\UkrPostApi\ApiModels\RequestData\CreateShipmentRequest;
use Sashalenz\UkrPostApi\ApiModels\RequestData\ParcelRequestData;
use Sashalenz\UkrPostApi\ApiModels\RequestData\ShipmentGroupRequestData;
use Sashalenz\UkrPostApi\Credentials;
use Sashalenz\UkrPostApi\Enums\DeliveryType;
use Sashalenz\UkrPostApi\Enums\GroupType;
use Sashalenz\UkrPostApi\Enums\ShipmentType;
use Sashalenz\UkrPostApi\UkrPostApi;

it('creates an address, clients, and a shipment in the Ukrposhta sandbox', function (): void {
    $bearer = getenv('UKRPOST_BEARER_ECOM');
    $token = getenv('UKRPOST_COUNTERPARTY_TOKEN');

    if (getenv('UKRPOST_RUN_SANDBOX') !== '1' || ! is_string($bearer) || $bearer === '' || ! is_string($token) || $token === '') {
        $this->markTestSkipped('Set UKRPOST_RUN_SANDBOX=1 and sandbox credentials to run the destructive sandbox flow.');
    }

    config(['ukrpost-api.sandbox' => true]);
    $credentials = new Credentials($bearer, $token, sandbox: true);
    $suffix = bin2hex(random_bytes(6));

    $address = UkrPostApi::addresses($credentials)->create([
        'postcode' => '01001',
        'country' => 'UA',
        'region' => 'Київ',
        'district' => 'Київ',
        'city' => 'Київ',
    ]);
    expect($address->id)->not->toBeNull();

    $sender = UkrPostApi::clients($credentials)->ensure([
        'type' => 'COMPANY',
        'name' => 'ТОВ Тест '.$suffix,
        'edrpou' => '32855961',
        'phoneNumber' => '0501234567',
        'addressId' => $address->id,
        'externalId' => 'ukrpost-sdk-sender-'.$suffix,
    ]);
    $recipient = UkrPostApi::clients($credentials)->ensure([
        'type' => 'INDIVIDUAL',
        'firstName' => 'Іван',
        'lastName' => 'Тестовий',
        'middleName' => 'Іванович',
        'phoneNumber' => '0507654321',
        'addressId' => $address->id,
        'externalId' => 'ukrpost-sdk-recipient-'.$suffix,
    ]);

    expect($sender->uuid)->not->toBeNull()
        ->and($recipient->uuid)->not->toBeNull();

    $shipment = UkrPostApi::shipments($credentials)->create(new CreateShipmentRequest(
        senderUuid: (string) $sender->uuid,
        recipientUuid: (string) $recipient->uuid,
        deliveryType: DeliveryType::W2W,
        type: ShipmentType::STANDARD,
        parcels: [new ParcelRequestData(1000, 30, 20, 10)],
    ));

    expect($shipment->uuid)->not->toBeNull()
        ->and($shipment->barcode)->not->toBeNull()->not->toBe('');

    $group = UkrPostApi::groups($credentials)->create(new ShipmentGroupRequestData(
        name: 'SDK sandbox '.$suffix,
        clientUuid: (string) $sender->uuid,
        type: GroupType::STANDARD,
    ));
    UkrPostApi::groups($credentials)->addShipment((string) $group->uuid, (string) $shipment->uuid);

    $stickers = UkrPostApi::documents($credentials)->stickersByBarcodes([(string) $shipment->barcode]);
    $form103a = UkrPostApi::documents($credentials)->form103a((string) $group->uuid);

    expect($stickers)->toStartWith('%PDF')
        ->and($form103a)->toStartWith('%PDF');
})->group('sandbox');
