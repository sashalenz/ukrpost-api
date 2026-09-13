<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels\RequestData;

use Sashalenz\UkrPostApi\Exceptions\UkrPostValidationException;
use Spatie\LaravelData\Data;

final class Form20eRequestData extends Data
{
    /** @param list<Form20eStateRequestData> $deliveredStates */
    public function __construct(
        public string $idcode,
        public array $deliveredStates,
    ) {}

    /** @return array<string, mixed> */
    public function toApiArray(): array
    {
        if ($this->deliveredStates === []) {
            throw new UkrPostValidationException('At least one delivery state is required for form 20e.');
        }

        return [
            'idcode' => $this->idcode,
            'deliveredStates' => array_map(
                static fn (Form20eStateRequestData $state): array => $state->toApiArray(),
                $this->deliveredStates,
            ),
        ];
    }
}
