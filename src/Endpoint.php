<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi;

enum Endpoint: string
{
    case ECOM = 'ecom';
    case FORMS = 'forms';
    case STATUS_TRACKING = 'status_tracking';
    case CLASSIFIER = 'classifier';

    public function requiresCounterpartyToken(): bool
    {
        return match ($this) {
            self::ECOM, self::FORMS => true,
            self::STATUS_TRACKING, self::CLASSIFIER => false,
        };
    }
}
