<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels\ResponseData;

use Spatie\LaravelData\Data;

final class RouteData extends Data
{
    public function __construct(
        public ?string $from = null,
        public ?string $to = null,
    ) {}
}
