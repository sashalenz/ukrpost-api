<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\Enums;

enum ShipmentStatus: string
{
    case CREATED = 'CREATED';
    case REGISTERED = 'REGISTERED';
    case DELIVERING = 'DELIVERING';
    case IN_DEPARTMENT = 'IN_DEPARTMENT';
    case STORAGE = 'STORAGE';
    case DELIVERED = 'DELIVERED';
    case FORWARDING = 'FORWARDING';
    case RETURNING = 'RETURNING';
    case RETURNED = 'RETURNED';
    case TRANSFERRED_COURIER = 'TRANSFERRED_COURIER';
    case CANCELED = 'CANCELED';
    case DELETED = 'DELETED';
}
