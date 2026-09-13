<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\Enums;

enum CourierOrderType: string
{
    case SINGLE = 'SINGLE';
    case MASS = 'MASS';
}
