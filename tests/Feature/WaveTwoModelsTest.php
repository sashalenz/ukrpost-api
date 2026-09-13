<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Sashalenz\UkrPostApi\Credentials;
use Sashalenz\UkrPostApi\Exceptions\UkrPostValidationException;
use Sashalenz\UkrPostApi\UkrPostApi;

it('exposes every Wave 2 read endpoint with centralized token handling', function (): void {
    Http::fake(fn (Request $request) => match (true) {
        str_contains($request->url(), '/shipments/') => Http::response(['status' => 'CREATED']),
        str_contains($request->url(), '/clients/') => Http::response(['uuid' => 'client']),
        str_contains($request->url(), '/addresses/') => Http::response(['id' => 42]),
        default => Http::response([]),
    });
    $credentials = new Credentials('bearer', 'token');

    UkrPostApi::addresses($credentials)->find(42);
    UkrPostApi::addresses($credentials)->availabilityChecking('01001', '79000');
    UkrPostApi::clients($credentials)->find('client/id');
    UkrPostApi::clients($credentials)->findByExternalId('external/id');
    UkrPostApi::clients($credentials)->findByPhone('0501234567');
    UkrPostApi::clients($credentials)->addresses('client-id');
    UkrPostApi::clients($credentials)->phones('client-id');
    UkrPostApi::shipments($credentials)->find('shipment/id');
    UkrPostApi::shipments($credentials)->findByBarcode('0500000000001');
    UkrPostApi::shipments($credentials)->lifecycle('shipment/id');
    UkrPostApi::shipments($credentials)->isPriceChangedInPostOffice('0500000000001');

    Http::assertSentCount(11);
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/clients/client%2Fid?token=token'));
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/clients/external-id/external%2Fid?token=token'));
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/shipments/shipment%2Fid/lifecycle?token=token'));
});

it('validates and sends address and client mutations', function (): void {
    Http::fake([
        '*/addresses*' => Http::response(['id' => 42]),
        '*/clients*' => Http::response(['uuid' => 'client']),
    ]);
    $credentials = new Credentials('bearer', 'token');

    UkrPostApi::addresses($credentials)->update(42, ['description' => 'Офіс']);
    UkrPostApi::clients($credentials)->create([
        'type' => 'COMPANY',
        'name' => 'ТОВ Приклад',
        'edrpou' => '32855961',
    ]);
    UkrPostApi::clients($credentials)->update('client', ['phoneNumber' => '0501234567']);

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT' && str_contains($request->url(), '/addresses/42?token=token'));
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), '/clients?token=token'));
});

it('stops invalid address and client mutations before HTTP', function (): void {
    Http::fake();
    $credentials = new Credentials('bearer', 'token');

    expect(fn () => UkrPostApi::addresses($credentials)->create(['postcode' => 'invalid']))
        ->toThrow(UkrPostValidationException::class)
        ->and(fn () => UkrPostApi::clients($credentials)->create([
            'type' => 'COMPANY',
            'name' => 'ТОВ Приклад',
            'edrpou' => '32855962',
        ]))->toThrow(UkrPostValidationException::class);

    Http::assertNothingSent();
});
