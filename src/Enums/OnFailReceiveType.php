<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\Enums;

enum OnFailReceiveType: string
{
    case RETURN = 'RETURN';
    case RETURN_AFTER_7_DAYS = 'RETURN_AFTER_7_DAYS';
    case RETURN_AFTER_5_DAYS = 'RETURN_AFTER_5_DAYS';
    case PROCESS_AS_REFUSAL = 'PROCESS_AS_REFUSAL';
}
