<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels\RequestData;

use Sashalenz\UkrPostApi\Enums\DeliveryStateType;
use Sashalenz\UkrPostApi\Enums\ReturnReason;
use Sashalenz\UkrPostApi\Enums\StorageReason;
use Sashalenz\UkrPostApi\Exceptions\UkrPostValidationException;
use Spatie\LaravelData\Data;

final class Form20eStateRequestData extends Data
{
    public function __construct(
        public DeliveryStateType $stateDeliveredType,
        public StorageReason|ReturnReason $reason,
    ) {}

    /** @return array<string, mixed> */
    public function toApiArray(): array
    {
        $valid = match ($this->stateDeliveredType) {
            DeliveryStateType::STORAGE => $this->reason instanceof StorageReason,
            DeliveryStateType::RETURNED => $this->reason instanceof ReturnReason,
        };

        if (! $valid) {
            throw new UkrPostValidationException('The form 20e reason does not match its delivery state.');
        }

        $reasonKey = $this->stateDeliveredType === DeliveryStateType::STORAGE
            ? 'ReasonStorage'
            : 'ReasonReturned';

        return [
            'stateDeliveredType' => $this->stateDeliveredType->value,
            'properties' => [$reasonKey => $this->reason->value],
        ];
    }
}
