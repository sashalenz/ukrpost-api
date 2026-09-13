<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels\RequestData;

use DateTimeImmutable;
use Sashalenz\UkrPostApi\Enums\CourierInterval;
use Sashalenz\UkrPostApi\Enums\CourierOrderType;
use Sashalenz\UkrPostApi\Exceptions\UkrPostValidationException;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class CourierOrderRequestData extends Data
{
    /**
     * @param  list<string>  $shipmentBarcodes
     * @param  list<string>  $letterBarcodes
     */
    public function __construct(
        public string $clientUuid,
        public CourierOrderType $type,
        public int $addressId,
        public int $phoneId,
        public string $dropDate,
        public CourierInterval $interval,
        public array $shipmentBarcodes = [],
        public array $letterBarcodes = [],
        public string|null|Optional $email = new Optional,
    ) {}

    /** @return array<string, mixed> */
    public function toApiArray(): array
    {
        $this->validateForApi();

        return array_filter([
            'clientUuid' => $this->clientUuid,
            'type' => $this->type->value,
            'addressId' => $this->addressId,
            'phoneId' => $this->phoneId,
            'dropDate' => $this->dropDate,
            'interval' => $this->interval->value,
            'shipmentBarcodes' => $this->shipmentBarcodes,
            'letterBarcodes' => $this->letterBarcodes,
            'email' => $this->email,
        ], static fn (mixed $value): bool => ! $value instanceof Optional);
    }

    private function validateForApi(): void
    {
        if (trim($this->clientUuid) === '' || $this->addressId < 1 || $this->phoneId < 1) {
            throw new UkrPostValidationException('Courier order requires a client, address, and phone.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $this->dropDate);
        if ($date === false || $date->format('Y-m-d') !== $this->dropDate) {
            throw new UkrPostValidationException('Courier dropDate must use the YYYY-MM-DD format.');
        }

        $barcodes = [...$this->shipmentBarcodes, ...$this->letterBarcodes];
        if ($barcodes === [] || count(array_filter($barcodes, static fn (string $barcode): bool => trim($barcode) === '')) > 0) {
            throw new UkrPostValidationException('Courier order requires non-empty shipment or letter barcodes.');
        }

        if ($this->type === CourierOrderType::SINGLE && count($barcodes) !== 1) {
            throw new UkrPostValidationException('A SINGLE courier order must contain exactly one barcode.');
        }

        if ($this->type === CourierOrderType::MASS && count($barcodes) < 10) {
            throw new UkrPostValidationException('A MASS courier order must contain at least ten barcodes.');
        }
    }
}
