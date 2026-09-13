<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Sashalenz\UkrPostApi\Credentials;
use Sashalenz\UkrPostApi\Enums\DeliveryType;
use Sashalenz\UkrPostApi\Exceptions\UkrPostValidationException;
use Sashalenz\UkrPostApi\UkrPostApi;

/** @return array<string, mixed> */
function managementShipment(array $overrides = []): array
{
    return array_replace([
        'uuid' => 'shipment-id',
        'barcode' => '0500101983180',
        'status' => 'REGISTERED',
        'type' => 'STANDARD',
        'postPay' => 500,
        'declaredPrice' => 1000,
        'postPayPaidByRecipient' => true,
        'returnAfterStorageDays' => 7,
        'onFailReceiveType' => 'RETURN',
        'personalHanding' => false,
        'sender' => ['uuid' => 'sender', 'type' => 'INDIVIDUAL', 'middleName' => 'Іванович'],
        'recipient' => ['uuid' => 'recipient', 'type' => 'INDIVIDUAL', 'firstName' => 'Іван', 'lastName' => 'Тестовий', 'middleName' => 'Іванович', 'addressId' => 42],
        'parcels' => [['declaredPrice' => 1000]],
    ], $overrides);
}

it('uses the documented management endpoint for each operation', function (string $operation, string $method, string $path): void {
    Http::fake([
        '*/shipments/barcode/*' => Http::response(managementShipment()),
        '*/shipments/shipment-id?*' => Http::response(managementShipment()),
        '*/clients/new-recipient?*' => Http::response([
            'uuid' => 'new-recipient', 'type' => 'INDIVIDUAL', 'firstName' => 'Іван', 'lastName' => 'Тестовий', 'middleName' => 'Іванович', 'addressId' => $operation === 'forward' ? 84 : 42,
        ]),
        '*/addresses/*' => Http::response(['id' => 42, 'postcode' => '01001']),
        '*get_postoffices_by_postindex*' => Http::response(['Entries' => ['Entry' => [['TYPE_SHORT' => 'МВ', 'RESTRICTED_ACCESS' => '0']]]]),
        '*' => Http::response($operation === 'return'
            ? ['response' => ['orders' => [['extr_guid_order' => 'order-guid', 'order_id' => 123]]], 'status' => ['code' => 0]]
            : managementShipment()),
    ]);
    $management = UkrPostApi::management(new Credentials('bearer', 'token'));

    match ($operation) {
        'storage' => $management->extendStorage('shipment-id', 8),
        'recipient' => $management->changeRecipient('shipment-id', 'new-recipient'),
        'forward' => $management->forward('shipment-id', 'new-recipient', DeliveryType::W2W),
        'postpay' => $management->changePostPay('shipment-id', 750),
        'return' => $management->createReturnOrder('0500101983180', 'sender-token', 7656280),
    };

    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === $method
        && str_contains($request->url(), $path));
})->with([
    'extend storage' => ['storage', 'PUT', '/shipments/management/shipment-id/return-after-storage-days/8?'],
    'change recipient' => ['recipient', 'PUT', '/shipments/management/shipment-id/recipient?'],
    'forward' => ['forward', 'PUT', '/shipments/management/shipment-id/forward?'],
    'change postpay' => ['postpay', 'PUT', '/shipments/management/shipment-id/postpay?'],
    'return order' => ['return', 'POST', '/dispatch/return-order?'],
]);

it('blocks every management operation in a disallowed status before mutation', function (string $operation): void {
    Http::fake(['*' => Http::response(managementShipment(['status' => 'DELIVERED']))]);
    $management = UkrPostApi::management(new Credentials('bearer', 'token'));

    expect(fn () => match ($operation) {
        'storage' => $management->extendStorage('shipment-id', 8),
        'recipient' => $management->changeRecipient('shipment-id', 'new-recipient'),
        'forward' => $management->forward('shipment-id', 'new-recipient', DeliveryType::W2W),
        'postpay' => $management->changePostPay('shipment-id', 100),
        'return' => $management->createReturnOrder('0500101983180', 'sender-token', 7656280),
    })->toThrow(UkrPostValidationException::class);

    Http::assertSentCount(1);
    Http::assertNotSent(fn (ClientRequest $request): bool => $request->method() !== 'GET');
})->with(['storage', 'recipient', 'forward', 'postpay', 'return']);

it('enforces operation-specific status boundaries', function (string $operation, string $status): void {
    Http::fake(['*' => Http::response(managementShipment(['status' => $status]))]);
    $management = UkrPostApi::management(new Credentials('bearer', 'token'));

    expect(fn () => match ($operation) {
        'recipient' => $management->changeRecipient('shipment-id', 'new-recipient'),
        'postpay' => $management->changePostPay('shipment-id', 100),
        'forward' => $management->forward('shipment-id', 'new-recipient', DeliveryType::W2W),
        'return' => $management->createReturnOrder('0500101983180', 'sender-token', 7656280),
    })->toThrow(UkrPostValidationException::class);

    Http::assertSentCount(1);
    Http::assertNotSent(fn (ClientRequest $request): bool => $request->method() !== 'GET');
})->with([
    'recipient at office' => ['recipient', 'IN_DEPARTMENT'],
    'postpay at office' => ['postpay', 'IN_DEPARTMENT'],
    'forward before registration' => ['forward', 'CREATED'],
    'return before registration' => ['return', 'CREATED'],
]);

it('allows postpay cancellation but validates its financial rules', function (array $shipment, float $amount, bool $allowed): void {
    Http::fake(['*' => Http::response(managementShipment($shipment))]);
    $management = UkrPostApi::management(new Credentials('bearer', 'token'));
    $call = fn () => $management->changePostPay('shipment-id', $amount);

    if ($allowed) {
        $call();
        Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'PUT'
            && $request->data()['postPay'] === $amount);

        return;
    }

    expect($call)->toThrow(UkrPostValidationException::class);
    Http::assertNotSent(fn (ClientRequest $request): bool => $request->method() === 'PUT');
})->with([
    'cancel' => [[], 0.0, true],
    'over API maximum' => [[], 30000.0, false],
    'over declared price' => [[], 1001.0, false],
    'sender-paid increase after creation' => [['postPayPaidByRecipient' => false], 501.0, false],
    'sender-paid decrease' => [['postPayPaidByRecipient' => false], 499.0, true],
    'created sender-paid increase' => [['status' => 'CREATED', 'postPayPaidByRecipient' => false], 750.0, true],
]);

it('requires middle names when changing postpay for individuals', function (string $role): void {
    $client = ['uuid' => $role, 'type' => 'INDIVIDUAL', 'middleName' => null];
    Http::fake(['*' => Http::response(managementShipment([$role => $client]))]);

    expect(fn () => UkrPostApi::management(new Credentials('bearer', 'token'))->changePostPay('shipment-id', 400))
        ->toThrow(UkrPostValidationException::class);
    Http::assertNotSent(fn (ClientRequest $request): bool => $request->method() === 'PUT');
})->with(['sender', 'recipient']);

it('rejects invalid storage extension rules before mutation', function (array $shipment, int $days): void {
    Http::fake(['*' => Http::response(managementShipment($shipment))]);

    expect(fn () => UkrPostApi::management(new Credentials('bearer', 'token'))->extendStorage('shipment-id', $days))
        ->toThrow(UkrPostValidationException::class);
    Http::assertNotSent(fn (ClientRequest $request): bool => $request->method() === 'PUT');
})->with([
    'cannot reduce' => [['returnAfterStorageDays' => 14], 13],
    'maximum' => [[], 29],
    'cargo fixed storage' => [['type' => 'CARGO'], 8],
    'process as refusal' => [['onFailReceiveType' => 'PROCESS_AS_REFUSAL'], 8],
]);

it('rejects recipient replacement that changes non-contact identity data', function (): void {
    Http::fake([
        '*/shipments/shipment-id?*' => Http::response(managementShipment()),
        '*/clients/new-recipient?*' => Http::response([
            'uuid' => 'new-recipient', 'type' => 'COMPANY', 'name' => 'Інша компанія', 'addressId' => 999,
        ]),
    ]);

    expect(fn () => UkrPostApi::management(new Credentials('bearer', 'token'))->changeRecipient('shipment-id', 'new-recipient'))
        ->toThrow(UkrPostValidationException::class);
    Http::assertNotSent(fn (ClientRequest $request): bool => $request->method() === 'PUT');
});

it('rejects recipient changes for international shipments before loading a replacement', function (): void {
    Http::fake(['*' => Http::response(managementShipment(['type' => 'INTERNATIONAL']))]);

    expect(fn () => UkrPostApi::management(new Credentials('bearer', 'token'))->changeRecipient('shipment-id', 'new-recipient'))
        ->toThrow(UkrPostValidationException::class);

    Http::assertSentCount(1);
});

it('allows forwarding an international import', function (): void {
    Http::fake([
        '*/shipments/shipment-id?*' => Http::response(managementShipment(['type' => 'INTERNATIONAL'])),
        '*/clients/new-recipient?*' => Http::response([
            'uuid' => 'new-recipient', 'type' => 'INDIVIDUAL', 'firstName' => 'Іван', 'lastName' => 'Тестовий', 'middleName' => 'Іванович', 'addressId' => 84,
        ]),
        '*' => Http::response(managementShipment(['type' => 'INTERNATIONAL'])),
    ]);

    UkrPostApi::management(new Credentials('bearer', 'token'))
        ->forward('shipment-id', 'new-recipient', DeliveryType::W2D);

    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'PUT'
        && str_contains($request->url(), '/shipments/management/shipment-id/forward?'));
});

it('rejects personal handing forwarding to a restricted office', function (array $office): void {
    Http::fake([
        '*/shipments/shipment-id?*' => Http::response(managementShipment(['personalHanding' => true])),
        '*/clients/new-recipient?*' => Http::response([
            'uuid' => 'new-recipient', 'type' => 'INDIVIDUAL', 'firstName' => 'Іван', 'lastName' => 'Тестовий', 'middleName' => 'Іванович', 'addressId' => 84,
        ]),
        '*/addresses/84?*' => Http::response(['id' => 84, 'postcode' => '01001']),
        '*get_postoffices_by_postindex*' => Http::response(['Entries' => ['Entry' => [$office]]]),
    ]);

    expect(fn () => UkrPostApi::management(new Credentials('bearer', 'token'))->forward(
        'shipment-id',
        'new-recipient',
        DeliveryType::W2W,
    ))->toThrow(UkrPostValidationException::class);
    Http::assertNotSent(fn (ClientRequest $request): bool => $request->method() === 'PUT');
})->with([
    'PUDO' => [['TYPE_SHORT' => 'PARTNER', 'RESTRICTED_ACCESS' => '0']],
    'parcel terminal' => [['TYPE_SHORT' => 'PARCEL_TERMINAL', 'RESTRICTED_ACCESS' => '0']],
    'restricted access' => [['TYPE_SHORT' => 'МВ', 'RESTRICTED_ACCESS' => '1']],
]);

it('enforces office-specific storage rules', function (array $shipment, array $office, int $days): void {
    Http::fake([
        '*/shipments/shipment-id?*' => Http::response(managementShipment($shipment)),
        '*/addresses/42?*' => Http::response(['id' => 42, 'postcode' => '01001']),
        '*get_postoffices_by_postindex*' => Http::response(['Entries' => ['Entry' => [$office]]]),
    ]);

    expect(fn () => UkrPostApi::management(new Credentials('bearer', 'token'))->extendStorage('shipment-id', $days))
        ->toThrow(UkrPostValidationException::class);
    Http::assertNotSent(fn (ClientRequest $request): bool => $request->method() === 'PUT');
})->with([
    'rural minimum is fifteen days' => [[], ['TYPE_SHORT' => 'СВ'], 14],
    'PUDO cannot be extended' => [[], ['TYPE_SHORT' => 'PARTNER'], 8],
    'mobile office after arrival' => [['status' => 'IN_DEPARTMENT'], ['TYPE_SHORT' => 'ПВ'], 15],
]);
