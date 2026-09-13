<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\Enums;

enum PostPayTransferStatus: string
{
    case NO = 'NO';
    case ZR = 'ZR';
    case VO = 'VO';
    case TV = 'TV';
    case DP = 'DP';
    case DL = 'DL';
    case DD = 'DD';
    case DO = 'DO';
    case OD = 'OD';
    case OP = 'OP';
    case PD = 'PD';
    case TD = 'TD';
    case FN = 'FN';
    case TN = 'TN';
    case ON = 'ON';
    case ZO = 'ZO';
    case ZN = 'ZN';
    case ZP = 'ZP';
    case PO = 'PO';
    case ZD = 'ZD';
    case DA = 'DA';
    case ZV = 'ZV';
    case VA = 'VA';
    case MS = 'MS';
    case MA = 'MA';
    case OZ = 'OZ';
    case UP = 'UP';

    public function isAvailableForPayment(): bool
    {
        return $this === self::DO || $this === self::OD;
    }

    public function isPaid(): bool
    {
        return $this === self::OP;
    }
}
