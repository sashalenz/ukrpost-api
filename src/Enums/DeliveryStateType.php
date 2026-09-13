<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\Enums;

enum DeliveryStateType: string
{
    case RETURNED = 'returned';
    case STORAGE = 'storage';
}
