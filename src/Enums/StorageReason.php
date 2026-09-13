<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\Enums;

enum StorageReason: string
{
    case WRONG_ADDRESS = 'WRONG_ADDRESS';
    case ADDRESSEE_MISSING = 'ADDRESSEE_MISSING';
    case REFUSAL_ADDRESSEE = 'REFUSAL_ADDRESSEE';
    case SENDERS_REQUESTED = 'SENDERS_REQUESTED';
    case RECIPIENT_REQUESTED = 'RECIPIENT_REQUESTED';
    case EXPIRY = 'EXPIRY';
    case OTHERS = 'OTHERS';
}
