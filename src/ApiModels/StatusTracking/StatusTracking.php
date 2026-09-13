<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels\StatusTracking;

use Illuminate\Support\Collection;
use Sashalenz\UkrPostApi\ApiModels\BaseModel;
use Sashalenz\UkrPostApi\ApiModels\ResponseData\RouteData;
use Sashalenz\UkrPostApi\ApiModels\ResponseData\StatusBatchResult;
use Sashalenz\UkrPostApi\ApiModels\ResponseData\StatusData;
use Sashalenz\UkrPostApi\Endpoint;
use Sashalenz\UkrPostApi\Exceptions\UkrPostValidationException;
use Sashalenz\UkrPostApi\Support\BarcodeValidator;

final class StatusTracking extends BaseModel
{
    protected Endpoint $endpoint = Endpoint::STATUS_TRACKING;

    /** @return Collection<int, StatusData> */
    public function statuses(string $barcode): Collection
    {
        BarcodeValidator::ensureTrackable($barcode);

        return collect(self::statusItems($this->get('/statuses', ['barcode' => $barcode])->all()));
    }

    public function lastStatus(string $barcode): StatusData
    {
        BarcodeValidator::ensureTrackable($barcode);

        return StatusData::from(self::object($this->get('/statuses/last', ['barcode' => $barcode])->all()));
    }

    public function route(string $barcode, bool $english = false): RouteData
    {
        BarcodeValidator::ensureTrackable($barcode);
        $path = '/barcodes/'.rawurlencode($barcode).'/route'.($english ? '/in-lang/EN' : '');

        return RouteData::from(self::object($this->get($path)->all()));
    }

    /** @param list<string> $barcodes */
    public function statusesBatch(array $barcodes): StatusBatchResult
    {
        return $this->batch($barcodes, 50, '/statuses/with-not-found');
    }

    /** @param list<string> $barcodes */
    public function lastStatusesBatch(array $barcodes): StatusBatchResult
    {
        return $this->batch($barcodes, 100, '/statuses/last/with-not-found');
    }

    public function extraStatuses(string $barcode, string $recipientPhoneNumber): StatusData
    {
        BarcodeValidator::ensureTrackable($barcode);

        return StatusData::from(self::object($this->get('/extra-statuses/last', [
            'barcode' => $barcode,
            'recipientPhoneNumber' => $recipientPhoneNumber,
        ])->all()));
    }

    public function insideLast(string $barcode, string $recipientPhoneNumber): StatusData
    {
        BarcodeValidator::ensureTrackable($barcode);

        return StatusData::from(self::object($this->get('/extra-statuses/inside/last', [
            'barcode' => $barcode,
            'recipientPhoneNumber' => $recipientPhoneNumber,
        ])->all()));
    }

    /** @param list<string> $barcodes */
    private function batch(array $barcodes, int $size, string $path): StatusBatchResult
    {
        if ($size < 1) {
            throw new \LogicException('Tracking batch size must be positive.');
        }

        $barcodes = array_values(array_unique($barcodes));
        foreach ($barcodes as $barcode) {
            BarcodeValidator::ensureTrackable($barcode);
        }

        $found = [];
        $notFound = [];

        foreach (array_chunk($barcodes, $size) as $chunk) {
            $response = self::object($this->post($path, $chunk)->all());
            $chunkFound = $response['found'] ?? [];

            if (! is_array($chunkFound)) {
                throw new UkrPostValidationException('Unexpected tracking batch response: found must be an object.');
            }

            foreach ($chunkFound as $barcode => $statuses) {
                if (! is_string($barcode)) {
                    throw new UkrPostValidationException('Unexpected tracking batch response: invalid barcode key.');
                }

                $found[$barcode] = [...($found[$barcode] ?? []), ...self::statusItems($statuses)];
            }

            $chunkNotFound = $response['notFound'] ?? [];
            if (! is_array($chunkNotFound)) {
                throw new UkrPostValidationException('Unexpected tracking batch response: notFound must be a list.');
            }

            foreach ($chunkNotFound as $barcode) {
                if (! is_string($barcode)) {
                    throw new UkrPostValidationException('Unexpected tracking batch response: invalid notFound barcode.');
                }

                $notFound[] = $barcode;
            }
        }

        return new StatusBatchResult($found, array_values(array_unique($notFound)));
    }

    /** @return list<StatusData> */
    private static function statusItems(mixed $value): array
    {
        if (! is_array($value)) {
            throw new UkrPostValidationException('Unexpected tracking response: statuses must be a list.');
        }

        $statuses = [];

        foreach ($value as $status) {
            $statuses[] = StatusData::from(self::object($status));
        }

        return $statuses;
    }

    /** @return array<string, mixed> */
    private static function object(mixed $value): array
    {
        if (! is_array($value)) {
            throw new UkrPostValidationException('Unexpected tracking response: expected an object.');
        }

        $result = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw new UkrPostValidationException('Unexpected tracking response: expected named fields.');
            }

            $result[$key] = $item;
        }

        return $result;
    }
}
