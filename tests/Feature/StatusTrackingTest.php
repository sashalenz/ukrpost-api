<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Sashalenz\UkrPostApi\ApiModels\ResponseData\RouteData;
use Sashalenz\UkrPostApi\ApiModels\ResponseData\StatusData;
use Sashalenz\UkrPostApi\Credentials;
use Sashalenz\UkrPostApi\Exceptions\UkrPostValidationException;
use Sashalenz\UkrPostApi\UkrPostApi;

function trackingCredentials(): Credentials
{
    return new Credentials('ecom-bearer', 'counterparty-token', 'status-bearer');
}

it('uses the dedicated tracking bearer and never adds a counterparty token', function (): void {
    Http::fake(['*' => Http::response([[
        'barcode' => '0500000000001',
        'event' => 10100,
        'eventReason_id' => 1,
    ]])]);

    $statuses = UkrPostApi::statusTracking(trackingCredentials())->statuses('0500000000001');

    expect($statuses)->toHaveCount(1)
        ->and($statuses->first())->toBeInstanceOf(StatusData::class);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer status-bearer')
        && $request->url() === 'https://www.ukrposhta.ua/status-tracking/0.0.1/statuses?barcode=0500000000001'
        && ! str_contains($request->url(), 'token='));
});

it('returns typed last status, route, and phone-protected extra statuses', function (): void {
    Http::fake(fn (Request $request) => match (true) {
        str_contains($request->url(), '/route') => Http::response(['from' => 'UKRAINE .Kyiv', 'to' => 'UKRAINE .Lviv']),
        str_contains($request->url(), '/inside/') => Http::response([
            'barcode' => '0500000000001',
            'event' => '41000',
            'eventReason_id' => 2,
            'height' => 17,
            'length' => 32,
            'width' => 31,
            'weight' => 7650,
            'payment' => 0.0,
            'sendingPaymentSum' => 8646,
        ]),
        default => Http::response(['barcode' => '0500000000001', 'event' => '41000', 'eventReason_id' => 2]),
    });
    $tracking = UkrPostApi::statusTracking(trackingCredentials());

    $last = $tracking->lastStatus('0500000000001');
    $route = $tracking->route('0500000000001', english: true);
    $extra = $tracking->extraStatuses('0500000000001', '380501234567');
    $inside = $tracking->insideLast('0500000000001', '380501234567');

    expect($last)->toBeInstanceOf(StatusData::class)
        ->and($route)->toBeInstanceOf(RouteData::class)
        ->and($route->from)->toBe('UKRAINE .Kyiv')
        ->and($extra)->toBeInstanceOf(StatusData::class)
        ->and($inside->weight)->toBe(7650)
        ->and($inside->sendingPaymentSum)->toBe(8646.0);
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/barcodes/0500000000001/route/in-lang/EN'));
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/extra-statuses/last?barcode=0500000000001&recipientPhoneNumber=380501234567'));
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/extra-statuses/inside/last?barcode=0500000000001&recipientPhoneNumber=380501234567'));
});

it('splits 150 last-status barcodes into two requests and retains not-found barcodes', function (): void {
    $barcodes = array_map(static fn (int $number): string => sprintf('%013d', $number), range(1, 150));
    $missing = [$barcodes[0], $barcodes[149]];

    Http::fake(function (Request $request) use ($missing) {
        $found = [];
        $notFound = [];

        foreach ($request->data() as $barcode) {
            if (! is_string($barcode)) {
                continue;
            }

            if (in_array($barcode, $missing, true)) {
                $notFound[] = $barcode;
            } else {
                $found[$barcode] = [['barcode' => $barcode, 'event' => 20700]];
            }
        }

        return Http::response(['found' => $found, 'notFound' => $notFound]);
    });

    $result = UkrPostApi::statusTracking(trackingCredentials())->lastStatusesBatch($barcodes);

    expect($result->found)->toHaveCount(148)
        ->and($result->notFound)->toBe($missing);
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && str_contains($request->url(), '/statuses/last/with-not-found')
        && count($request->data()) === 100);
    Http::assertSent(fn (Request $request): bool => count($request->data()) === 50);
});

it('chunks full-history batches by 50', function (): void {
    Http::fake(['*' => Http::response(['found' => [], 'notFound' => []])]);
    $barcodes = array_map(static fn (int $number): string => sprintf('%013d', $number), range(1, 51));

    UkrPostApi::statusTracking(trackingCredentials())->statusesBatch($barcodes);

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/statuses/with-not-found'));
});

it('rejects unsupported international barcodes before HTTP', function (): void {
    Http::fake();

    expect(fn () => UkrPostApi::statusTracking(trackingCredentials())->lastStatus('UU123456789CN'))
        ->toThrow(UkrPostValidationException::class);

    Http::assertNothingSent();
});
