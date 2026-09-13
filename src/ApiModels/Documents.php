<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels;

use Illuminate\Support\Collection;
use Sashalenz\UkrPostApi\ApiModels\RequestData\Form119eRequestData;
use Sashalenz\UkrPostApi\ApiModels\RequestData\Form20eRequestData;
use Sashalenz\UkrPostApi\Endpoint;
use Sashalenz\UkrPostApi\Enums\FormSize;
use Sashalenz\UkrPostApi\Exceptions\UkrPostValidationException;

final class Documents extends BaseModel
{
    protected Endpoint $endpoint = Endpoint::FORMS;

    public function sticker(
        string $uuidOrBarcode,
        ?FormSize $size = null,
        bool $hideDeliveryPrice = false,
        bool $hidePostPayDeliveryPrice = false,
        bool $hideWeight = false,
        bool $hideDeclaredPrice = false,
    ): string {
        return $this->binary('/shipments/'.rawurlencode($uuidOrBarcode).'/sticker', self::stickerQuery(
            $size,
            $hideDeliveryPrice,
            $hidePostPayDeliveryPrice,
            $hideWeight,
            $hideDeclaredPrice,
        ));
    }

    public function stickerA4(string $uuidOrBarcode): string
    {
        return $this->sticker($uuidOrBarcode, FormSize::A4);
    }

    public function stickerA5(string $uuidOrBarcode): string
    {
        return $this->sticker($uuidOrBarcode, FormSize::A5);
    }

    public function groupSticker(string $groupUuid, ?FormSize $size = null): string
    {
        return $this->binary('/shipment-groups/'.rawurlencode($groupUuid).'/sticker', self::sizeQuery($size));
    }

    public function parcelSticker(string $parcelBarcode): string
    {
        return $this->binary('/shipments/sticker/parcel/'.rawurlencode($parcelBarcode));
    }

    /**
     * @param  list<string>|array<string, array<string, bool|int|string>>  $shipments
     */
    public function stickersByBarcodes(array $shipments, ?FormSize $size = null): string
    {
        if ($shipments === []) {
            throw new UkrPostValidationException('At least one shipment is required for batch sticker printing.');
        }

        $payload = [];
        foreach ($shipments as $id => $options) {
            if (is_int($id)) {
                if (! is_string($options) || $options === '') {
                    throw new UkrPostValidationException('Every batch sticker shipment must have a UUID or barcode.');
                }

                $payload[$options] = [];

                continue;
            }

            if (! is_array($options)) {
                throw new UkrPostValidationException('Batch sticker options must be an object.');
            }

            $payload[$id] = self::batchStickerOptions($options);
        }

        return $this->binary('/shipments/stickers-by-barcodes', self::sizeQuery($size), 'POST', $payload);
    }

    public function form103a(string $groupUuid, bool $showSenderName = false, bool $hideDeclaredPrice = false): string
    {
        return $this->binary('/shipment-groups/'.rawurlencode($groupUuid).'/form103a', array_filter([
            'showSenderName' => $showSenderName ? 'true' : null,
            'hideDeclaredPrice' => $hideDeclaredPrice ? '1' : null,
        ], static fn (?string $value): bool => $value !== null));
    }

    public function form119(string $uuidOrBarcode): string
    {
        return $this->binary('/shipments/'.rawurlencode($uuidOrBarcode).'/form119');
    }

    /** @param array<string, mixed> $data */
    public function form119e(Form119eRequestData|array $data, bool $asPdf = true): string
    {
        $payload = $data instanceof Form119eRequestData ? $data->toApiArray() : $data;

        return $this->binary('/shipments/form119e', ['frmOption' => $asPdf ? 1 : 0], 'POST', $payload);
    }

    /** @param array<string, mixed> $data */
    public function form20e(Form20eRequestData|array $data, bool $asPdf = true): string
    {
        $payload = $data instanceof Form20eRequestData ? $data->toApiArray() : $data;

        return $this->binary('/shipments/form20e', ['frmOption' => $asPdf ? 1 : 0], 'POST', $payload);
    }

    public function groupForm119(string $groupUuid): string
    {
        return $this->binary('/shipment-groups/'.rawurlencode($groupUuid).'/form119');
    }

    public function deliveryNotificationForm119(string $uuidOrBarcode): string
    {
        return $this->binary('/shipments/delivery-notifications/'.rawurlencode($uuidOrBarcode).'/form119');
    }

    public function form107(string $uuidOrBarcode): string
    {
        return $this->binary('/shipments/'.rawurlencode($uuidOrBarcode).'/form107');
    }

    public function groupForm107(string $groupUuid): string
    {
        return $this->binary('/shipment-groups/'.rawurlencode($groupUuid).'/form107');
    }

    /** @return Collection<int, mixed> */
    public function shipmentFormJson(string $barcode): Collection
    {
        return $this->get('/domestic/shipment-forms/'.rawurlencode($barcode).'/json');
    }

    /** @return Collection<int, mixed> */
    public function shipmentDocumentFormJson(string $barcode): Collection
    {
        return $this->get('/domestic/shipment-forms/'.rawurlencode($barcode).'/document-json');
    }

    /** @return Collection<int, mixed> */
    public function shipmentGroupFormJson(string $barcode): Collection
    {
        return $this->get('/shipment-group-forms/'.rawurlencode($barcode).'/json');
    }

    /** @return array<string, string> */
    private static function stickerQuery(
        ?FormSize $size,
        bool $hideDeliveryPrice,
        bool $hidePostPayDeliveryPrice,
        bool $hideWeight,
        bool $hideDeclaredPrice,
    ): array {
        return array_filter([
            ...self::sizeQuery($size),
            'hideDeliveryPrice' => $hideDeliveryPrice ? '1' : null,
            'hidePostPayDeliveryPrice' => $hidePostPayDeliveryPrice ? '1' : null,
            'hideWeight' => $hideWeight ? '1' : null,
            'hideDeclaredPrice' => $hideDeclaredPrice ? '1' : null,
        ], static fn (?string $value): bool => $value !== null);
    }

    /** @return array<string, string> */
    private static function sizeQuery(?FormSize $size): array
    {
        return $size === null ? [] : ['size' => $size->value];
    }

    /**
     * @param  array<string, bool|int|string>  $options
     * @return array<string, string>
     */
    private static function batchStickerOptions(array $options): array
    {
        $allowed = ['hideWeight', 'hidePostpayDeliveryPrice', 'hideDeliveryPrice'];
        $normalized = [];

        foreach ($options as $key => $value) {
            if (! in_array($key, $allowed, true)) {
                throw new UkrPostValidationException('Unsupported batch sticker option: '.$key.'.');
            }

            if (is_bool($value)) {
                $normalized[$key] = $value ? '1' : '0';

                continue;
            }

            if (! in_array((string) $value, ['0', '1'], true)) {
                throw new UkrPostValidationException('Batch sticker options accept only boolean, 0, or 1.');
            }

            $normalized[$key] = (string) $value;
        }

        return $normalized;
    }
}
