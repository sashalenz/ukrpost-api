<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\Enums;

enum ShipmentType: string
{
    case EXPRESS = 'EXPRESS';
    case STANDARD = 'STANDARD';
    case DOCUMENT = 'DOCUMENT';
    case CARGO = 'CARGO';
    case INTERNATIONAL = 'INTERNATIONAL';
    case VALUABLE_LETTER = 'VALUABLE_LETTER';
}
