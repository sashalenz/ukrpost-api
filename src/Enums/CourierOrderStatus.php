<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\Enums;

enum CourierOrderStatus: string
{
    case ORDERED = 'ORDERED';
    case TO_PERFORM = 'TO_PERFORM';
    case TRANSFERRED_TO_COURIER = 'TRANSFERRED_TO_COURIER';
    case DONE = 'DONE';
    case POSTPONED = 'POSTPONED';
    case DENIED = 'DENIED';
    case NOT_DELIVERED = 'NOT_DELIVERED';
}
