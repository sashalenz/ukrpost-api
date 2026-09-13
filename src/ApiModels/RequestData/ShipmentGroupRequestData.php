<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels\RequestData;

use Sashalenz\UkrPostApi\Enums\GroupType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class ShipmentGroupRequestData extends Data
{
    public function __construct(
        public string $name,
        public string|null|Optional $clientUuid = new Optional,
        public GroupType $type = GroupType::EXPRESS,
    ) {}

    /** @return array<string, mixed> */
    public function toApiArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'clientUuid' => $this->clientUuid,
            'type' => $this->type->value,
        ], static fn (mixed $value): bool => ! $value instanceof Optional);
    }
}
