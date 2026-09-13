<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\Enums;

enum PostPayPaymentType: string
{
    case CASH_ONLY = 'POSTPAY_PAYMENT_CASH_ONLY';
    case CASHLESS_ONLY = 'POSTPAY_PAYMENT_CASHLESS_ONLY';
    case CASH_AND_CASHLESS = 'POSTPAY_PAYMENT_CASH_AND_CASHLESS';
}
