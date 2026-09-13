<?php

declare(strict_types=1);

use Sashalenz\UkrPostApi\ApiModels\ResponseData\StatusData;
use Sashalenz\UkrPostApi\Enums\EventCode;
use Sashalenz\UkrPostApi\Enums\ShipmentStatus;
use Sashalenz\UkrPostApi\Exceptions\UkrPostValidationException;

it('distinguishes a return to sender from delivery for event 41000', function (): void {
    expect(EventCode::toStatus(41000, 10))->toBe(ShipmentStatus::RETURNED)
        ->and(EventCode::toStatus(41000))->toBe(ShipmentStatus::DELIVERED);
});

it('maps documented tracking events to eCom statuses', function (int $event, ShipmentStatus $status): void {
    expect(EventCode::toStatus($event))->toBe($status);
})->with([
    [10601, ShipmentStatus::CREATED],
    [10100, ShipmentStatus::REGISTERED],
    [10600, ShipmentStatus::CANCELED],
    [10603, ShipmentStatus::DELETED],
    [20700, ShipmentStatus::DELIVERING],
    [21400, ShipmentStatus::STORAGE],
    [21700, ShipmentStatus::IN_DEPARTMENT],
    [31200, ShipmentStatus::RETURNING],
    [31300, ShipmentStatus::FORWARDING],
    [48000, ShipmentStatus::DELIVERED],
]);

it('supports configured event overrides without guessing unknown statuses', function (): void {
    config(['ukrpost-api.event_map' => ['99999' => 'STORAGE']]);

    expect(EventCode::toStatus(99999))->toBe(ShipmentStatus::STORAGE)
        ->and(fn () => EventCode::toStatus(88888))->toThrow(UkrPostValidationException::class);
});

it('hydrates eventReason_id and maps numeric-string events from the API', function (): void {
    $status = StatusData::from(['barcode' => '0500000000001', 'event' => '41000', 'eventReason_id' => 10]);

    expect($status->event)->toBe(41000)
        ->and($status->eventReason_id)->toBe(10)
        ->and($status->shipmentStatus())->toBe(ShipmentStatus::RETURNED);
});
