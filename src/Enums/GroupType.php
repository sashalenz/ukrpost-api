<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\Enums;

enum GroupType: string
{
    case EXPRESS = 'EXPRESS';
    case STANDARD = 'STANDARD';
    case DOCUMENT = 'DOCUMENT';
    case CARGO = 'CARGO';
    case VALUABLE_LETTER = 'VALUABLE_LETTER';
}
