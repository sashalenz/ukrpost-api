<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Sashalenz\UkrPostApi\Credentials;
use Sashalenz\UkrPostApi\Endpoint;
use Sashalenz\UkrPostApi\Exceptions\UkrPostApiUnavailableException;
use Sashalenz\UkrPostApi\Request;

function credentials(): Credentials
{
    return new Credentials('ecom-bearer', 'counterparty-token', 'tracking-bearer');
}

it('selects URL and bearer per endpoint', function (): void {
    Http::fake(fn (ClientRequest $request) => Http::response(['ok' => true]));
    new Request(credentials(), Endpoint::STATUS_TRACKING, 'GET', 'statuses/last')->make();
    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://www.ukrposhta.ua/status-tracking/0.0.1/statuses/last' && $request->header('Authorization') === ['Bearer tracking-bearer']);
});

it('adds token only to ecom and forms', function (Endpoint $endpoint, bool $hasToken): void {
    Http::fake(fn (ClientRequest $request) => Http::response(['ok' => true]));
    new Request(credentials(), $endpoint, 'GET', 'resource')->make();
    Http::assertSent(fn (ClientRequest $request): bool => $hasToken === str_contains($request->url(), 'token=counterparty-token'));
})->with([[Endpoint::ECOM, true], [Endpoint::FORMS, true], [Endpoint::CLASSIFIER, false], [Endpoint::STATUS_TRACKING, false]]);

it('does not retry POST after a server error', function (): void {
    Http::fake(fn (ClientRequest $request) => Http::response(['message' => 'down'], 500));
    expect(fn () => new Request(credentials(), Endpoint::ECOM, 'POST', 'shipments')->make())->toThrow(UkrPostApiUnavailableException::class);
    expect(Http::recorded()->count())->toBe(1);
});

it('retries GET after a server error', function (): void {
    Http::fakeSequence()->pushStatus(500)->pushStatus(500)->push(['ok' => true]);
    expect(new Request(credentials(), Endpoint::STATUS_TRACKING, 'GET', 'statuses/last')->make()->get('ok'))->toBeTrue();
    expect(Http::recorded()->count())->toBe(3);
});
