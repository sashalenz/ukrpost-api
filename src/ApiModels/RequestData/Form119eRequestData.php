<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels\RequestData;

use Sashalenz\UkrPostApi\Enums\ElectronicDeliveryType;
use Sashalenz\UkrPostApi\Exceptions\UkrPostValidationException;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class Form119eRequestData extends Data
{
    public function __construct(
        public string $idcode,
        public ElectronicDeliveryType $deliveredType,
        public string $deliveredDate,
        public string|null|Optional $recipientName = new Optional,
    ) {}

    /** @return array<string, string> */
    public function toApiArray(): array
    {
        if ($this->deliveredType->requiresRecipientName()
            && (! is_string($this->recipientName) || trim($this->recipientName) === '')) {
            throw new UkrPostValidationException('recipientName is required for this electronic delivery type.');
        }

        $payload = [
            'idcode' => $this->idcode,
            'deliveredType' => $this->deliveredType->value,
            'deliveredDate' => $this->deliveredDate,
        ];

        if (is_string($this->recipientName)) {
            $payload['recipientName'] = $this->recipientName;
        }

        return $payload;
    }
}
