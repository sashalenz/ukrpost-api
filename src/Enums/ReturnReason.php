<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\Enums;

enum ReturnReason: string
{
    case EXPIRY = 'EXPIRY';
    case REFUSAL_RECEIVE = 'REFUSAL_RECEIVE';
    case OTHERS = 'OTHERS';
}
