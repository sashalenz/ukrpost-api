# sashalenz/ukrpost-api

Typed Laravel SDK for the Ukrposhta eCom, Forms, StatusTracking, and AddressClassifier APIs.

## Installation

```bash
composer require sashalenz/ukrpost-api
```

Publish the configuration and provide Ukrposhta credentials:

```bash
php artisan vendor:publish --tag="ukrpost-api-config"
```

```dotenv
UKRPOST_BEARER_ECOM=
UKRPOST_COUNTERPARTY_TOKEN=
UKRPOST_BEARER_STATUS_TRACKING=
UKRPOST_COUNTERPARTY_UUID=
UKRPOST_SANDBOX=false
```

## Address, client, and shipment flow

```php
use Sashalenz\UkrPostApi\ApiModels\RequestData\CreateShipmentRequest;
use Sashalenz\UkrPostApi\ApiModels\RequestData\ParcelRequestData;
use Sashalenz\UkrPostApi\Enums\DeliveryType;
use Sashalenz\UkrPostApi\Enums\ShipmentType;
use Sashalenz\UkrPostApi\UkrPostApi;

$address = UkrPostApi::addresses()->create([
    'postcode' => '01001',
    'country' => 'UA',
    'region' => 'Київ',
    'district' => 'Київ',
    'city' => 'Київ',
]);

$recipient = UkrPostApi::clients()->ensure([
    'type' => 'INDIVIDUAL',
    'firstName' => 'Іван',
    'lastName' => 'Петренко',
    'middleName' => 'Іванович',
    'phoneNumber' => '0501234567',
    'addressId' => $address->id,
    'externalId' => 'customer-42',
]);

$shipment = UkrPostApi::shipments()->create(new CreateShipmentRequest(
    senderUuid: 'sender-uuid',
    recipientUuid: $recipient->uuid,
    deliveryType: DeliveryType::W2W,
    type: ShipmentType::STANDARD,
    parcels: [new ParcelRequestData(
        weight: 1000,
        length: 30,
        width: 20,
        height: 10,
    )],
));

$barcode = $shipment->barcode;
```

`Client::ensure()` reuses a client found by `externalId` or phone and creates one only after a confirmed miss. Shipment mutations re-read the current status and are allowed only while it is `CREATED`.

The SDK validates parcel dimensions and weight, post-pay limits, courier and mailbox addresses, mobile-office parcel counts, and Ukrainian tax identifiers before a write request. Only `GET` and `HEAD` requests are retried; non-idempotent shipment creation is never retried.

## Status tracking

StatusTracking uses its own bearer and never sends the eCom counterparty token:

```php
$tracking = UkrPostApi::statusTracking();

$last = $tracking->lastStatus($barcode);
$status = $last->shipmentStatus();
$route = $tracking->route($barcode);

$batch = $tracking->lastStatusesBatch($barcodes); // chunks by 100
$found = $batch->found;
$notFound = $batch->notFound;

$inside = $tracking->insideLast($barcode, '380501234567');
```

`statusesBatch()` chunks full-history requests by 50. Both batch methods use the `with-not-found` endpoints, so absent barcodes remain visible. Event `41000` with `eventReason_id = 10` maps to `RETURNED`, while other `41000` events map to `DELIVERED`.

## Sandbox integration test

The opt-in test creates sandbox records and verifies that shipment creation returns a barcode:

```bash
UKRPOST_RUN_SANDBOX=1 composer test -- --group=sandbox
```

It also requires `UKRPOST_BEARER_ECOM` and `UKRPOST_COUNTERPARTY_TOKEN` for the sandbox account.

## Quality checks

```bash
composer format
composer analyse
composer test
```

## License

MIT.
