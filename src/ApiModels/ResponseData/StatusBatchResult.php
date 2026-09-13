<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels\ResponseData;

final readonly class StatusBatchResult
{
    /**
     * @param  array<string, list<StatusData>>  $found
     * @param  list<string>  $notFound
     */
    public function __construct(
        public array $found,
        public array $notFound,
    ) {}
}
