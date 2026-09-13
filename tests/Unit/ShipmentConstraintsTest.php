<?php

declare(strict_types=1);

use Sashalenz\UkrPostApi\Exceptions\UkrPostValidationException;
use Sashalenz\UkrPostApi\Support\BarcodeValidator;
use Sashalenz\UkrPostApi\Support\ShipmentConstraints;
use Sashalenz\UkrPostApi\Support\ShipmentValidationContext;

function shipmentPayload(array $overrides = []): array
{
    return array_replace([
        'deliveryType' => 'W2W',
        'type' => 'STANDARD',
        'sender' => ['type' => 'INDIVIDUAL', 'middleName' => 'Петрович'],
        'recipient' => ['type' => 'INDIVIDUAL'],
        'parcels' => [['weight' => 1000, 'length' => 30, 'width' => 20, 'height' => 10, 'declaredPrice' => 100000]],
    ], $overrides);
}

it('enforces parcel and total weight boundaries', function (): void {
    ShipmentConstraints::validate(shipmentPayload(['parcels' => [['weight' => 30000, 'length' => 30, 'width' => 20, 'height' => 10]]]));

    expect(fn () => ShipmentConstraints::validate(shipmentPayload(['parcels' => [['weight' => 30001, 'length' => 30, 'width' => 20, 'height' => 10]]])))
        ->toThrow(UkrPostValidationException::class)
        ->and(fn () => ShipmentConstraints::validate(shipmentPayload(['parcels' => array_fill(0, 34, ['weight' => 30000, 'length' => 30, 'width' => 20, 'height' => 10])])))
        ->toThrow(UkrPostValidationException::class);
});

it('enforces Express and Standard dimension boundaries', function (): void {
    ShipmentConstraints::validate(shipmentPayload(['parcels' => [['weight' => 1000, 'length' => 120, 'width' => 70, 'height' => 70]]]));

    expect(fn () => ShipmentConstraints::validate(shipmentPayload(['parcels' => [['weight' => 1000, 'length' => 121, 'width' => 70, 'height' => 70]]])))
        ->toThrow(UkrPostValidationException::class);
});

it('enforces Cargo largest-side and sum boundaries', function (): void {
    ShipmentConstraints::validate(shipmentPayload(['type' => 'CARGO', 'parcels' => [['weight' => 1000, 'length' => 150, 'width' => 100, 'height' => 100]]]));

    expect(fn () => ShipmentConstraints::validate(shipmentPayload(['type' => 'CARGO', 'parcels' => [['weight' => 1000, 'length' => 119, 'width' => 100, 'height' => 100]]])))
        ->toThrow(UkrPostValidationException::class)
        ->and(fn () => ShipmentConstraints::validate(shipmentPayload(['type' => 'CARGO', 'parcels' => [['weight' => 1000, 'length' => 120, 'width' => 60, 'height' => 60]]])))
        ->toThrow(UkrPostValidationException::class)
        ->and(fn () => ShipmentConstraints::validate(shipmentPayload(['type' => 'CARGO', 'parcels' => [['weight' => 1000, 'length' => 151, 'width' => 100, 'height' => 100]]])))
        ->toThrow(UkrPostValidationException::class);
});

it('allows only one parcel for Cargo', function (): void {
    $parcel = ['weight' => 1000, 'length' => 150, 'width' => 100, 'height' => 100];

    expect(fn () => ShipmentConstraints::validate(shipmentPayload(['type' => 'CARGO', 'parcels' => [$parcel, $parcel]])))
        ->toThrow(UkrPostValidationException::class);
});

it('requires width and height', function (): void {
    ShipmentConstraints::validate(shipmentPayload());

    expect(fn () => ShipmentConstraints::validate(shipmentPayload(['parcels' => [['weight' => 1000, 'length' => 30, 'height' => 10]]])))
        ->toThrow(UkrPostValidationException::class);
});

it('enforces cash and cashless postPay limits', function (): void {
    ShipmentConstraints::validate(shipmentPayload(['postPay' => 50000]));
    ShipmentConstraints::validate(shipmentPayload([
        'postPay' => 100000,
        'transferPostPayToBankAccount' => true,
        'sender' => ['type' => 'COMPANY', 'postPayPaymentType' => 'POSTPAY_PAYMENT_CASHLESS_ONLY'],
    ]));

    expect(fn () => ShipmentConstraints::validate(shipmentPayload(['postPay' => 50000.01])))
        ->toThrow(UkrPostValidationException::class)
        ->and(fn () => ShipmentConstraints::validate(shipmentPayload([
            'postPay' => 100000.01,
            'transferPostPayToBankAccount' => true,
            'sender' => ['type' => 'COMPANY', 'postPayPaymentType' => 'POSTPAY_PAYMENT_CASHLESS_ONLY'],
        ])))
        ->toThrow(UkrPostValidationException::class);
});

it('does not allow shipment postPay to exceed its declared price', function (): void {
    ShipmentConstraints::validate(shipmentPayload([
        'postPay' => 100,
        'parcels' => [['weight' => 1000, 'length' => 30, 'width' => 20, 'height' => 10, 'declaredPrice' => 100]],
    ]));

    expect(fn () => ShipmentConstraints::validate(shipmentPayload([
        'postPay' => 100.01,
        'parcels' => [['weight' => 1000, 'length' => 30, 'width' => 20, 'height' => 10, 'declaredPrice' => 100]],
    ])))->toThrow(UkrPostValidationException::class);
});

it('enforces postPay change range and declared price', function (): void {
    ShipmentConstraints::validatePostPayChange(0, 100);
    ShipmentConstraints::validatePostPayChange(29999, 29999);

    expect(fn () => ShipmentConstraints::validatePostPayChange(-0.01, 100))
        ->toThrow(UkrPostValidationException::class)
        ->and(fn () => ShipmentConstraints::validatePostPayChange(101, 100))
        ->toThrow(UkrPostValidationException::class);
});

it('limits Document declared price', function (): void {
    ShipmentConstraints::validate(shipmentPayload(['type' => 'DOCUMENT', 'parcels' => [['weight' => 1000, 'length' => 30, 'width' => 20, 'height' => 10, 'declaredPrice' => 300]]]));

    expect(fn () => ShipmentConstraints::validate(shipmentPayload(['type' => 'DOCUMENT', 'parcels' => [['weight' => 1000, 'length' => 30, 'width' => 20, 'height' => 10, 'declaredPrice' => 300.01]]])))
        ->toThrow(UkrPostValidationException::class);
});

it('allows neither multiple parcels nor postPay for Document', function (): void {
    $parcel = ['weight' => 1000, 'length' => 30, 'width' => 20, 'height' => 10];

    expect(fn () => ShipmentConstraints::validate(shipmentPayload(['type' => 'DOCUMENT', 'parcels' => [$parcel, $parcel]])))
        ->toThrow(UkrPostValidationException::class)
        ->and(fn () => ShipmentConstraints::validate(shipmentPayload([
            'type' => 'DOCUMENT',
            'postPay' => 100,
            'sender' => ['type' => 'INDIVIDUAL', 'middleName' => 'Петрович'],
            'parcels' => [$parcel],
        ])))->toThrow(UkrPostValidationException::class);
});

it('requires street and house number for courier delivery', function (): void {
    $validAddress = ['street' => 'Хрещатик', 'houseNumber' => '1'];
    ShipmentConstraints::validate(shipmentPayload(['deliveryType' => 'W2D']), new ShipmentValidationContext(recipientAddress: $validAddress));

    expect(fn () => ShipmentConstraints::validate(
        shipmentPayload(['deliveryType' => 'W2D']),
        new ShipmentValidationContext(recipientAddress: ['street' => 'Хрещатик']),
    ))->toThrow(UkrPostValidationException::class);
});

it('keeps mailbox mutually exclusive with street addressing and courier delivery', function (): void {
    ShipmentConstraints::validate(shipmentPayload(), new ShipmentValidationContext(recipientAddress: ['mailbox' => '12']));

    expect(fn () => ShipmentConstraints::validate(
        shipmentPayload(),
        new ShipmentValidationContext(recipientAddress: ['mailbox' => '12', 'street' => 'Хрещатик']),
    ))->toThrow(UkrPostValidationException::class)
        ->and(fn () => ShipmentConstraints::validate(
            shipmentPayload(['deliveryType' => 'W2D']),
            new ShipmentValidationContext(recipientAddress: ['mailbox' => '12']),
        ))->toThrow(UkrPostValidationException::class);
});

it('limits every courier and mobile-office shipment to five parcels', function (string $deliveryType): void {
    $sixSmall = array_fill(0, 6, ['weight' => 1000, 'length' => 70, 'width' => 20, 'height' => 10]);
    expect(fn () => ShipmentConstraints::validate(shipmentPayload(['deliveryType' => $deliveryType, 'parcels' => $sixSmall])))
        ->toThrow(UkrPostValidationException::class)
        ->and(fn () => ShipmentConstraints::validate(
            shipmentPayload(['parcels' => $sixSmall]),
            new ShipmentValidationContext(recipientPostOfficeMobile: true),
        ))->toThrow(UkrPostValidationException::class);
})->with(['W2D', 'D2W', 'D2D']);

it('allows more than five parcels without courier service or a mobile post office', function (): void {
    $parcels = array_fill(0, 6, ['weight' => 1000, 'length' => 70, 'width' => 20, 'height' => 10]);
    $parcels[0]['declaredPrice'] = 1;

    ShipmentConstraints::validate(shipmentPayload([
        'deliveryType' => 'W2W',
        'parcels' => $parcels,
    ]));

    expect(true)->toBeTrue();
});

it('requires a declared price on at least one parcel of a multi-parcel shipment', function (): void {
    $parcel = ['weight' => 1000, 'length' => 30, 'width' => 20, 'height' => 10];
    ShipmentConstraints::validate(shipmentPayload(['parcels' => [$parcel, [...$parcel, 'declaredPrice' => 1]]]));

    expect(fn () => ShipmentConstraints::validate(shipmentPayload(['parcels' => [$parcel, $parcel]])))
        ->toThrow(UkrPostValidationException::class);
});

it('requires middle name for an individual participating in postPay', function (): void {
    ShipmentConstraints::validate(shipmentPayload([
        'postPay' => 100,
        'sender' => ['type' => 'INDIVIDUAL', 'middleName' => 'Петрович'],
    ]));

    expect(fn () => ShipmentConstraints::validate(shipmentPayload([
        'postPay' => 100,
        'sender' => ['type' => 'INDIVIDUAL'],
    ])))->toThrow(UkrPostValidationException::class);
});

it('does not require recipient middle name when creating a postPay shipment', function (): void {
    ShipmentConstraints::validate(shipmentPayload([
        'postPay' => 100,
        'recipient' => ['type' => 'INDIVIDUAL'],
    ]));

    expect(true)->toBeTrue();
});

it('rejects postPay for a legal-entity recipient and cash payout for a legal-entity sender', function (): void {
    expect(fn () => ShipmentConstraints::validate(shipmentPayload([
        'postPay' => 100,
        'recipient' => ['type' => 'COMPANY'],
    ])))->toThrow(UkrPostValidationException::class)
        ->and(fn () => ShipmentConstraints::validate(shipmentPayload([
            'postPay' => 100,
            'sender' => ['type' => 'PRIVATE_ENTREPRENEUR'],
        ])))->toThrow(UkrPostValidationException::class);
});

it('filters unsupported U and L tracking routes', function (): void {
    expect(BarcodeValidator::isTrackable('UU123456789UA'))->toBeTrue()
        ->and(BarcodeValidator::isTrackable('LO123456789CN'))->toBeTrue();

    expect(BarcodeValidator::isTrackable('UU123456789US'))->toBeFalse()
        ->and(BarcodeValidator::isTrackable('LO123456789FR'))->toBeFalse();
});
