<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Sashalenz\UkrPostApi\Credentials;
use Sashalenz\UkrPostApi\UkrPostApi;

it('uses the classifier endpoint, keeps uppercase fields, and unwraps Entries', function (): void {
    Cache::flush();
    Http::fake(fn (ClientRequest $request) => Http::response([
        'Entries' => ['Entry' => [['REGION_ID' => '270', 'REGION_UA' => 'Київська']]],
    ]));

    $result = UkrPostApi::classifier(new Credentials('ecom-bearer', 'counterparty-token'))
        ->regions('Київська')
        ->first();

    expect($result)->toBe(['REGION_ID' => '270', 'REGION_UA' => 'Київська']);
    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://www.ukrposhta.ua/address-classifier-ws/get_regions_by_region_ua?region_name=%D0%9A%D0%B8%D1%97%D0%B2%D1%81%D1%8C%D0%BA%D0%B0'
        && $request->header('Authorization') === ['Bearer ecom-bearer']
        && ! str_contains($request->url(), 'token='));
});

it('caches classifier lookups by default', function (): void {
    Cache::flush();
    Http::fake(fn (ClientRequest $request) => Http::response(['Entries' => ['Entry' => [['ID' => '1']]]]));
    $classifier = UkrPostApi::classifier(new Credentials('ecom-bearer', 'counterparty-token'));

    $classifier->postOfficesByPostindex(['pi' => '01001']);
    $classifier->postOfficesByPostindex(['pi' => '01001']);

    expect(Http::recorded()->count())->toBe(1);
});
