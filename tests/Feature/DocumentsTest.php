<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Sashalenz\UkrPostApi\ApiModels\RequestData\Form119eRequestData;
use Sashalenz\UkrPostApi\ApiModels\RequestData\Form20eRequestData;
use Sashalenz\UkrPostApi\ApiModels\RequestData\Form20eStateRequestData;
use Sashalenz\UkrPostApi\Credentials;
use Sashalenz\UkrPostApi\Enums\DeliveryStateType;
use Sashalenz\UkrPostApi\Enums\ElectronicDeliveryType;
use Sashalenz\UkrPostApi\Enums\FormSize;
use Sashalenz\UkrPostApi\Enums\StorageReason;
use Sashalenz\UkrPostApi\Exceptions\UkrPostValidationException;
use Sashalenz\UkrPostApi\UkrPostApi;

it('returns a binary sticker from the forms service', function (): void {
    Http::fake(['*' => Http::response('%PDF-binary', 200, ['Content-Type' => 'application/pdf'])]);

    $pdf = UkrPostApi::documents(new Credentials('ecom-bearer', 'token'))->stickerA4('shipment-id');

    expect($pdf)->toBe('%PDF-binary');
    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'GET'
        && $request->url() === 'https://www.ukrposhta.ua/forms/ecom/0.0.1/shipments/shipment-id/sticker?size=SIZE_A4&token=token'
        && $request->header('Authorization') === ['Bearer ecom-bearer']);
});

it('prints stickers by barcodes as one binary response', function (): void {
    Http::fake(['*' => Http::response('%PDF-batch')]);

    $pdf = UkrPostApi::documents(new Credentials('bearer', 'token'))->stickersByBarcodes([
        '0500101983180' => [],
        '0500101984900' => ['hideWeight' => true],
    ], FormSize::A5);

    expect($pdf)->toBe('%PDF-batch');
    Http::assertSentCount(1);
    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
        && str_contains($request->url(), '/shipments/stickers-by-barcodes?size=SIZE_A5&token=token')
        && $request->data() === [
            '0500101983180' => [],
            '0500101984900' => ['hideWeight' => '1'],
        ]);
});

it('passes form visibility flags centrally', function (): void {
    Http::fake(['*' => Http::response('%PDF-103a')]);

    UkrPostApi::documents(new Credentials('bearer', 'token'))->form103a(
        'group-id',
        showSenderName: true,
        hideDeclaredPrice: true,
    );

    Http::assertSent(fn (ClientRequest $request): bool => str_contains(
        $request->url(),
        '/shipment-groups/group-id/form103a?showSenderName=true&hideDeclaredPrice=1&token=token',
    ));
});

it('gets JSON form data without using the binary path', function (): void {
    Http::fake(['*' => Http::response(['barcode' => '0500101983180'])]);

    $form = UkrPostApi::documents(new Credentials('bearer', 'token'))->shipmentFormJson('0500101983180');

    expect($form->get('barcode'))->toBe('0500101983180');
});

it('builds electronic forms from typed API enums', function (): void {
    Http::fake(['*' => Http::response('%PDF')]);
    $documents = UkrPostApi::documents(new Credentials('bearer', 'token'));

    $documents->form119e(new Form119eRequestData(
        idcode: '0500113014256',
        deliveredType: ElectronicDeliveryType::FAMILY_MEMBER,
        deliveredDate: '20.11.2024',
        recipientName: 'Одержувач Тестовий',
    ));
    $documents->form20e(new Form20eRequestData('0500113014256', [
        new Form20eStateRequestData(DeliveryStateType::STORAGE, StorageReason::EXPIRY),
    ]));

    Http::assertSent(fn (ClientRequest $request): bool => ($request->data()['deliveredType'] ?? null) === 'FAMILY_MEMBER');
    Http::assertSent(fn (ClientRequest $request): bool => ($request->data()['deliveredStates'][0]['properties'] ?? null) === ['ReasonStorage' => 'EXPIRY']);
});

it('requires recipient name for non-personal form 119e delivery', function (): void {
    Http::fake();

    expect(fn () => UkrPostApi::documents(new Credentials('bearer', 'token'))->form119e(
        new Form119eRequestData('0500113014256', ElectronicDeliveryType::WARRANTY_PERSON, '20.11.2024'),
    ))->toThrow(UkrPostValidationException::class);

    Http::assertNothingSent();
});

it('uses documented binary form endpoints', function (string $method, string $expectedPath): void {
    Http::fake(['*' => Http::response('%PDF')]);
    $documents = UkrPostApi::documents(new Credentials('bearer', 'token'));

    $result = match ($method) {
        'groupSticker' => $documents->groupSticker('group-id'),
        'parcelSticker' => $documents->parcelSticker('parcel-id'),
        'form119' => $documents->form119('shipment-id'),
        'groupForm119' => $documents->groupForm119('group-id'),
        'deliveryNotificationForm119' => $documents->deliveryNotificationForm119('notification-id'),
        'form107' => $documents->form107('shipment-id'),
        'groupForm107' => $documents->groupForm107('group-id'),
    };

    expect($result)->toBe('%PDF');
    Http::assertSent(fn (ClientRequest $request): bool => str_contains($request->url(), $expectedPath));
})->with([
    'group sticker' => ['groupSticker', '/shipment-groups/group-id/sticker?'],
    'parcel sticker' => ['parcelSticker', '/shipments/sticker/parcel/parcel-id?'],
    'form 119' => ['form119', '/shipments/shipment-id/form119?'],
    'group form 119' => ['groupForm119', '/shipment-groups/group-id/form119?'],
    'notification form 119' => ['deliveryNotificationForm119', '/shipments/delivery-notifications/notification-id/form119?'],
    'form 107' => ['form107', '/shipments/shipment-id/form107?'],
    'group form 107' => ['groupForm107', '/shipment-groups/group-id/form107?'],
]);

it('uses both remaining JSON form variants', function (string $method, string $expectedPath): void {
    Http::fake(['*' => Http::response(['ok' => true])]);
    $documents = UkrPostApi::documents(new Credentials('bearer', 'token'));

    $result = match ($method) {
        'document' => $documents->shipmentDocumentFormJson('barcode'),
        'group' => $documents->shipmentGroupFormJson('barcode'),
    };

    expect($result->get('ok'))->toBeTrue();
    Http::assertSent(fn (ClientRequest $request): bool => str_contains($request->url(), $expectedPath));
})->with([
    'document JSON' => ['document', '/domestic/shipment-forms/barcode/document-json?'],
    'group JSON' => ['group', '/shipment-group-forms/barcode/json?'],
]);
