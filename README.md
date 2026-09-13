# sashalenz/ukrpost-api

Typed Laravel SDK for the Ukrposhta eCom, Forms, StatusTracking, and Address
Classifier APIs.

The package targets PHP 8.4+ and Laravel 11–13. It provides typed request and
response DTOs, PHP enums for documented API values, local validation for unsafe
operations, and separate authentication for each Ukrposhta service.

## Installation

```bash
composer require sashalenz/ukrpost-api
```

Laravel discovers the service provider automatically. Publish the configuration:

```bash
php artisan vendor:publish --tag="ukrpost-api-config"
```

Configure the credentials issued by Ukrposhta:

```dotenv
UKRPOST_BEARER_ECOM=
UKRPOST_COUNTERPARTY_TOKEN=
UKRPOST_BEARER_STATUS_TRACKING=
UKRPOST_COUNTERPARTY_UUID=
UKRPOST_SANDBOX=false

UKRPOST_TIMEOUT=15
UKRPOST_RETRY_TIMES=3
UKRPOST_RETRY_SLEEP=200
UKRPOST_CACHE_TTL=3600
```

`UKRPOST_BEARER_STATUS_TRACKING` is intentionally separate from the eCom bearer.
The counterparty token is added centrally to eCom and Forms requests, but never
to AddressClassifier or StatusTracking requests.

## Multiple accounts

Every API entry point accepts explicit credentials, so one process can safely
work with multiple Ukrposhta accounts or environments:

```php
use Sashalenz\UkrPostApi\Credentials;
use Sashalenz\UkrPostApi\UkrPostApi;

$credentials = new Credentials(
    bearerEcom: 'ecom-bearer',
    counterpartyToken: 'counterparty-token',
    bearerStatusTracking: 'status-bearer',
    counterpartyUuid: 'counterparty-uuid',
    sandbox: true,
);

$shipments = UkrPostApi::shipments($credentials);
```

Without explicit credentials, values are read from `config/ukrpost-api.php`.

## Address classifier

Classifier lookups can be cached. Shipment and other mutable records are never
cached by the SDK.

```php
$cities = UkrPostApi::classifier()
    ->cache(3600)
    ->cities(regionId: '11', districtId: '101', cityUa: 'Київ');

$offices = UkrPostApi::classifier()
    ->postOfficesByPostindex(['pi' => '01001']);
```

The classifier includes regions, districts, cities, streets, houses, courier
areas, post offices, opening hours, postcode, KOATUU, and geolocation lookups.

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

`Client::ensure()` reuses a client found by external ID or phone and creates one
only after a confirmed miss. The SDK validates documented address, parcel,
postpay, tax identifier, and shipment constraints before sending writes.

## Status tracking

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

`statusesBatch()` chunks full-history requests by 50. Both batch methods preserve
not-found barcodes. Event `41000` with reason `10` maps to `RETURNED`; other
`41000` events map to `DELIVERED`.

## Shipment groups and documents

```php
use Sashalenz\UkrPostApi\ApiModels\RequestData\ShipmentGroupRequestData;
use Sashalenz\UkrPostApi\Enums\FormSize;
use Sashalenz\UkrPostApi\Enums\GroupType;

$group = UkrPostApi::groups()->create(new ShipmentGroupRequestData(
    name: 'Orders 2026-09-13',
    clientUuid: 'sender-uuid',
    type: GroupType::STANDARD,
));

UkrPostApi::groups()->addShipment($group->uuid, $shipment->uuid);

$pdf = UkrPostApi::documents()->sticker($shipment->uuid, FormSize::A4);
file_put_contents(storage_path('app/labels/'.$barcode.'.pdf'), $pdf);
```

Forms are returned as binary strings through a dedicated transport path; PDF
responses are never converted to collections.

## Delivery price and postpay transfer

```php
use Sashalenz\UkrPostApi\ApiModels\RequestData\DeliveryParcelRequestData;
use Sashalenz\UkrPostApi\ApiModels\RequestData\DeliveryPriceRequest;

$price = UkrPostApi::calculation()->domestic(new DeliveryPriceRequest(
    addressFromPostcode: '01001',
    addressToPostcode: '79000',
    type: ShipmentType::STANDARD,
    deliveryType: DeliveryType::W2W,
    parcels: [new DeliveryParcelRequestData(
        weight: 1000,
        length: 30,
        width: 20,
        height: 10,
    )],
));

$transfer = UkrPostApi::transfers()->postPayStatus($barcode);
```

The same raw Ukrposhta endpoint powers domestic, multi-parcel, and document
calculations; the request body determines the calculation type.

## Managing a registered shipment

```php
$management = UkrPostApi::management();

$management->extendStorage($shipment->uuid, 14);
$management->changeRecipient($shipment->uuid, 'replacement-recipient-uuid');
$management->forward($shipment->uuid, 'forward-recipient-uuid', DeliveryType::W2W);
$management->changePostPay($shipment->uuid, 750.00);
$management->createReturnOrder($barcode, 'sender-token', 7656280);
```

Each operation reloads shipment state and enforces its own status, shipment type,
recipient, office, and postpay rules before the mutation. A postpay value of `0`
cancels postpay, as required by the current raw API documentation.

## Courier pickup

```php
use Sashalenz\UkrPostApi\ApiModels\RequestData\CourierOrderRequestData;
use Sashalenz\UkrPostApi\Enums\CourierInterval;
use Sashalenz\UkrPostApi\Enums\CourierOrderType;

$courier = UkrPostApi::courier();

$order = $courier->createOrder(new CourierOrderRequestData(
    clientUuid: 'sender-uuid',
    type: CourierOrderType::SINGLE,
    addressId: 1357331,
    phoneId: 387865,
    dropDate: '2026-09-14',
    interval: CourierInterval::INTERVAL_09_12,
    shipmentBarcodes: [$barcode],
));

$current = $courier->order($order->uuid);
$history = $courier->statuses($order->uuid);
$clientOrders = $courier->ordersByClient('sender-uuid');
```

`SINGLE` accepts exactly one barcode. `MASS` requires at least ten shipment or
letter barcodes.

## Endpoint coverage

| Service | Covered v1.0 areas |
|---|---|
| AddressClassifier | Regions, districts, cities, streets, houses, postcodes, offices, opening hours, courier zones, KOATUU, geolocation |
| eCom addresses | Create, find, update, route availability |
| eCom clients | Ensure, create, find, update, external ID/phone search, addresses, phones, postpay recipients |
| eCom shipments | Create, batch/group create, find, update/delete guards, lifecycle, parcels, groups, price changes |
| eCom shipment groups | Create/batch create, find, update, shipment membership, count, client lookup |
| eCom calculation/transfers | Domestic, multi-parcel and document prices; postpay transfer status |
| eCom management | Storage extension, recipient change, forwarding, postpay correction/cancellation, return order |
| eCom courier service | Create and read pickup orders, status history, client orders |
| Forms | Stickers, batch/group forms, 103a, 107, 119/119e, 20e, JSON forms |
| StatusTracking | Full/last status, route, batch queries, extra and internal recipient-authorized status |

Low-level access for endpoints outside this table is available through
`UkrPostApi::request()` while retaining centralized authentication, retry, and
error handling.

## Transport safety

- Only `GET` and `HEAD` are retried. Writes are never retried because shipment
  creation and other POST requests can incur duplicate barcodes and real costs.
- The eCom counterparty token is attached centrally according to the selected
  endpoint. Models do not append it manually.
- StatusTracking uses its own bearer token.
- Classifier caching is opt-in; mutable eCom resources are not cached.
- API failures are converted to typed package exceptions.

## Sandbox integration test

The opt-in integration test creates sandbox records and verifies that shipment
creation returns a barcode:

```bash
UKRPOST_RUN_SANDBOX=1 composer test -- --group=sandbox
```

It requires sandbox values for `UKRPOST_BEARER_ECOM` and
`UKRPOST_COUNTERPARTY_TOKEN`.

## Quality checks

```bash
composer format
composer analyse
composer test
```

## License

The MIT License. See [LICENSE.md](LICENSE.md).
