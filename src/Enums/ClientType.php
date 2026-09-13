<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\Enums;

enum ClientType: string
{
    case INDIVIDUAL = 'INDIVIDUAL';
    case COMPANY = 'COMPANY';
    case PRIVATE_ENTREPRENEUR = 'PRIVATE_ENTREPRENEUR';
}
