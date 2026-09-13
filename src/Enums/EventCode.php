<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\Enums;

use Sashalenz\UkrPostApi\Exceptions\UkrPostValidationException;

enum EventCode: int
{
    case REGISTERED = 10100;
    case CANCELED_BY_SENDER = 10600;
    case CREATED = 10601;
    case CANCELED = 10602;
    case DELETED = 10603;
    case ARRIVED_AT_SORTING_CENTER = 20700;
    case DISPATCHED = 20800;
    case DISPATCHED_TO_POST_OFFICE = 20900;
    case STORAGE = 21400;
    case DISPATCHED_TO_DELIVERY_POINT = 21500;
    case IN_DEPARTMENT = 21700;
    case DELIVERY_FAILED = 31100;
    case RETURNING = 31200;
    case FORWARDING = 31300;
    case FORWARDING_AFTER_FAILED_DELIVERY = 31400;
    case DELIVERED = 41000;
    case DELIVERED_IN_DESTINATION_COUNTRY = 48000;

    public static function toStatus(int $event, ?int $eventReasonId = null): ShipmentStatus
    {
        // Ukrposhta encodes a return-to-sender as delivery event 41000 plus reason 10, not as event 41010.
        if ($event === self::DELIVERED->value && $eventReasonId === 10) {
            return ShipmentStatus::RETURNED;
        }

        $override = self::configuredStatus($event, $eventReasonId);
        if ($override !== null) {
            return $override;
        }

        return match (self::tryFrom($event)) {
            self::CREATED => ShipmentStatus::CREATED,
            self::REGISTERED => ShipmentStatus::REGISTERED,
            self::CANCELED_BY_SENDER, self::CANCELED => ShipmentStatus::CANCELED,
            self::DELETED => ShipmentStatus::DELETED,
            self::ARRIVED_AT_SORTING_CENTER,
            self::DISPATCHED,
            self::DISPATCHED_TO_POST_OFFICE,
            self::DISPATCHED_TO_DELIVERY_POINT,
            self::DELIVERY_FAILED => ShipmentStatus::DELIVERING,
            self::STORAGE => ShipmentStatus::STORAGE,
            self::IN_DEPARTMENT => ShipmentStatus::IN_DEPARTMENT,
            self::RETURNING => ShipmentStatus::RETURNING,
            self::FORWARDING,
            self::FORWARDING_AFTER_FAILED_DELIVERY => ShipmentStatus::FORWARDING,
            self::DELIVERED,
            self::DELIVERED_IN_DESTINATION_COUNTRY => ShipmentStatus::DELIVERED,
            null => throw new UkrPostValidationException('Unsupported tracking event code: '.$event),
        };
    }

    private static function configuredStatus(int $event, ?int $eventReasonId): ?ShipmentStatus
    {
        $map = config('ukrpost-api.event_map', []);
        if (! is_array($map)) {
            return null;
        }

        $keys = $eventReasonId === null ? [(string) $event] : [$event.':'.$eventReasonId, (string) $event];

        foreach ($keys as $key) {
            if (! array_key_exists($key, $map)) {
                continue;
            }

            $value = $map[$key];
            $status = $value instanceof ShipmentStatus ? $value : (is_string($value) ? ShipmentStatus::tryFrom($value) : null);

            return $status ?? throw new UkrPostValidationException('Invalid configured shipment status for tracking event '.$key.'.');
        }

        return null;
    }
}
