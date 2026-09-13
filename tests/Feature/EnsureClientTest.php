<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Sashalenz\UkrPostApi\Credentials;
use Sashalenz\UkrPostApi\Exceptions\UkrPostApiUnavailableException;
use Sashalenz\UkrPostApi\Exceptions\UkrPostValidationException;
use Sashalenz\UkrPostApi\UkrPostApi;

it('reuses a client found by externalId', function (): void {
    Http::fake(['*' => Http::response(['uuid' => 'existing'])]);
    $client = UkrPostApi::clients(new Credentials('bearer', 'token'))->ensure(['externalId' => 'local-id']);
    expect($client->uuid)->toBe('existing');
    Http::assertSentCount(1);
    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
});

it('creates only after a confirmed missing client', function (): void {
    Http::fake([
        '*/clients/external-id/*' => Http::response(['code' => 'UPE02000'], 404),
        '*/clients?*' => Http::response(['uuid' => 'new-client']),
    ]);
    expect(UkrPostApi::clients(new Credentials('bearer', 'token'))->ensure([
        'externalId' => 'local-id',
        'type' => 'COMPANY',
        'name' => 'ТОВ Приклад',
        'edrpou' => '32855961',
    ])->uuid)->toBe('new-client');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST');
});

it('does not create after failed externalId lookup', function (): void {
    config(['ukrpost-api.retry_sleep' => 0]);
    Http::fake(['*' => Http::response([], 500)]);
    expect(fn () => UkrPostApi::clients(new Credentials('bearer', 'token'))->ensure(['externalId' => 'local-id']))->toThrow(UkrPostApiUnavailableException::class);
    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
});

it('reuses the single client found by phone', function (): void {
    Http::fake([
        '*/clients/phone*' => Http::response([['uuid' => 'existing']]),
        '*/clients/existing*' => Http::response(['uuid' => 'existing']),
    ]);

    $client = UkrPostApi::clients(new Credentials('bearer', 'token'))->ensure(['phoneNumber' => '0501234567']);

    expect($client->uuid)->toBe('existing');
    Http::assertSentCount(2);
    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
});

it('rejects an ambiguous phone match instead of creating a duplicate', function (): void {
    Http::fake(['*/clients/phone*' => Http::response([['uuid' => 'first'], ['uuid' => 'second']])]);

    expect(fn () => UkrPostApi::clients(new Credentials('bearer', 'token'))->ensure(['phoneNumber' => '0501234567']))
        ->toThrow(UkrPostValidationException::class);

    Http::assertSentCount(1);
    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
});
