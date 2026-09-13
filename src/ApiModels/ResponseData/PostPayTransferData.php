<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels\ResponseData;

use Sashalenz\UkrPostApi\Enums\PostPayTransferStatus;
use Spatie\LaravelData\Data;

final class PostPayTransferData extends Data
{
    public function __construct(
        public ?string $recipientName = null,
        public ?string $number = null,
        public ?float $sum = null,
        public ?PostPayTransferStatus $lastStatus = null,
        public ?string $lastStatusNameUa = null,
        public ?string $lastStatusNameEn = null,
        public ?string $lastStatusNameRu = null,
        public ?string $lastStatusTime = null,
    ) {}
}
