<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\Enums;

enum ElectronicDeliveryType: string
{
    case PERSONALLY = 'PERSONALLY';
    case FAMILY_MEMBER = 'FAMILY_MEMBER';
    case WARRANTY_PERSON = 'WARRANTY_PERSON';

    public function requiresRecipientName(): bool
    {
        return $this !== self::PERSONALLY;
    }
}
