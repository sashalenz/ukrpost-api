<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Sashalenz\UkrPostApi\ApiModels\RequestData\DeliveryParcelRequestData;
use Sashalenz\UkrPostApi\ApiModels\RequestData\DeliveryPriceRequest;
use Sashalenz\UkrPostApi\ApiModels\RequestData\DiscountRequestData;
use Sashalenz\UkrPostApi\Credentials;
use Sashalenz\UkrPostApi\Enums\DeliveryType;
use Sashalenz\UkrPostApi\Enums\PostPayTransferStatus;
use Sashalenz\UkrPostApi\Enums\ShipmentType;
use Sashalenz\UkrPostApi\Exceptions\UkrPostValidationException;
use Sashalenz\UkrPostApi\UkrPostApi;

function deliveryPriceRequest(ShipmentType $type = ShipmentType::STANDARD, int $parcelCount = 1): DeliveryPriceRequest
{
    return new DeliveryPriceRequest(
        addressFromPostcode: '04071',
        addressToPostcode: '79013',
        type: $type,
        deliveryType: DeliveryType::W2W,
        parcels: array_fill(0, $parcelCount, new DeliveryParcelRequestData(1000, 30, 20, 10, 15)),
        discounts: [new DiscountRequestData('Contract', 20)],
        validate: true,
    );
}

it('calculates domestic delivery with explicit discounts and packaging', function (): void {
    Http::fake(['*' => Http::response([
        'deliveryPrice' => 100,
        'rawDeliveryPrice' => 125,
        'postPayDeliveryPrice' => 5,
        'returnDeliveryPrice' => 15,
        'calculationDescription' => 'packagingPrice=15.00; Discount 20%=25.00',
        'discounts' => [['description' => 'Contract', 'rate' => 20]],
    ])]);

    $price = UkrPostApi::calculation(new Credentials('bearer', 'token'))->domestic(deliveryPriceRequest());

    expect($price->deliveryPrice)->toBe(100.0)
        ->and($price->rawDeliveryPrice)->toBe(125.0)
        ->and($price->discounts)->toHaveCount(1);
    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
        && str_contains($request->url(), '/domestic/delivery-price?token=token')
        && $request->data()['parcels'][0]['packagingPrice'] === 15.0
        && $request->data()['discounts'][0]['rate'] === 20.0);
});

it('uses the current raw endpoint for multi parcel and document calculations', function (string $method, Closure $request): void {
    Http::fake(['*' => Http::response(['deliveryPrice' => 40])]);

    $calculation = UkrPostApi::calculation(new Credentials('bearer', 'token'));
    match ($method) {
        'multi' => $calculation->multiParcel($request()),
        'document' => $calculation->document($request()),
    };

    Http::assertSent(fn (ClientRequest $sent): bool => str_contains($sent->url(), '/domestic/delivery-price?token=token'));
})->with([
    'multi parcel' => ['multi', fn (): DeliveryPriceRequest => deliveryPriceRequest(parcelCount: 2)],
    'document' => ['document', fn (): DeliveryPriceRequest => deliveryPriceRequest(ShipmentType::DOCUMENT)],
]);

it('rejects an invalid specialized calculation before HTTP', function (string $method, Closure $request): void {
    Http::fake();
    $calculation = UkrPostApi::calculation(new Credentials('bearer', 'token'));

    expect(fn () => $method === 'multi'
        ? $calculation->multiParcel($request())
        : $calculation->document($request()))->toThrow(UkrPostValidationException::class);
    Http::assertNothingSent();
})->with([
    'multi needs multiple parcels' => ['multi', fn (): DeliveryPriceRequest => deliveryPriceRequest()],
    'document needs document type' => ['document', fn (): DeliveryPriceRequest => deliveryPriceRequest()],
]);

it('maps the post pay transfer status from the current raw API', function (): void {
    Http::fake(['*' => Http::response([
        'recipientName' => 'Одержувач',
        'number' => '0202202258',
        'sum' => 527,
        'lastStatus' => 'OP',
        'lastStatusNameUa' => 'виплачений',
        'lastStatusTime' => '2026-09-12T10:00:00',
    ])]);

    $transfer = UkrPostApi::transfers(new Credentials('bearer', 'token'))->postPayStatus('0500101983180');

    expect($transfer->lastStatus)->toBe(PostPayTransferStatus::OP)
        ->and($transfer->sum)->toBe(527.0);
    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'GET'
        && str_contains($request->url(), '/transfers/shipment-postpays/0500101983180/with-recipient?token=token'));
});

it('contains every transfer status from the current raw table', function (): void {
    expect(PostPayTransferStatus::cases())->toHaveCount(27)
        ->and(PostPayTransferStatus::DO->isAvailableForPayment())->toBeTrue()
        ->and(PostPayTransferStatus::OD->isAvailableForPayment())->toBeTrue()
        ->and(PostPayTransferStatus::OP->isPaid())->toBeTrue();
});

it('manages post pay recipients on clients', function (string $operation, string $httpMethod): void {
    Http::fake(['*' => Http::response($operation === 'list' ? [] : ['message' => 'ok'])]);
    $clients = UkrPostApi::clients(new Credentials('bearer', 'token'));

    match ($operation) {
        'list' => $clients->postPayRecipients('client/id'),
        'add' => $clients->addPostPayRecipient('client/id', 'recipient/id'),
        'remove' => $clients->removePostPayRecipient('client/id', 'recipient/id'),
    };

    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === $httpMethod
        && str_contains($request->url(), '/clients/client%2Fid/post-pay-recipients'));
})->with([
    'list' => ['list', 'GET'],
    'add' => ['add', 'POST'],
    'remove' => ['remove', 'DELETE'],
]);
