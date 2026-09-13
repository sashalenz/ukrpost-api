<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels\ShipmentManagement;

use Illuminate\Support\Collection;
use Sashalenz\UkrPostApi\ApiModels\AddressClassifier;
use Sashalenz\UkrPostApi\ApiModels\BaseModel;
use Sashalenz\UkrPostApi\ApiModels\ResponseData\AddressData;
use Sashalenz\UkrPostApi\ApiModels\ResponseData\ClientData;
use Sashalenz\UkrPostApi\ApiModels\ResponseData\ParcelData;
use Sashalenz\UkrPostApi\ApiModels\ResponseData\ShipmentData;
use Sashalenz\UkrPostApi\Enums\ClientType;
use Sashalenz\UkrPostApi\Enums\DeliveryType;
use Sashalenz\UkrPostApi\Enums\OnFailReceiveType;
use Sashalenz\UkrPostApi\Enums\ShipmentStatus;
use Sashalenz\UkrPostApi\Enums\ShipmentType;
use Sashalenz\UkrPostApi\Exceptions\UkrPostValidationException;
use Sashalenz\UkrPostApi\Support\ShipmentConstraints;

final class ShipmentManagement extends BaseModel
{
    private const STORAGE_STATUSES = [
        ShipmentStatus::CREATED,
        ShipmentStatus::REGISTERED,
        ShipmentStatus::DELIVERING,
        ShipmentStatus::TRANSFERRED_COURIER,
        ShipmentStatus::IN_DEPARTMENT,
    ];

    private const RECIPIENT_STATUSES = [
        ShipmentStatus::CREATED,
        ShipmentStatus::REGISTERED,
        ShipmentStatus::DELIVERING,
        ShipmentStatus::TRANSFERRED_COURIER,
    ];

    private const FORWARD_AND_RETURN_STATUSES = [
        ShipmentStatus::REGISTERED,
        ShipmentStatus::DELIVERING,
        ShipmentStatus::IN_DEPARTMENT,
        ShipmentStatus::TRANSFERRED_COURIER,
    ];

    /** @var list<ShipmentType> */
    private const POSTPAY_TYPES = [ShipmentType::EXPRESS, ShipmentType::STANDARD, ShipmentType::CARGO];

    public function extendStorage(string $shipmentUuid, int $days): ShipmentData
    {
        $shipment = $this->shipment($shipmentUuid);
        self::ensureStatus($shipment, self::STORAGE_STATUSES, 'storage extension');

        if ($shipment->type === ShipmentType::CARGO) {
            throw new UkrPostValidationException('CARGO storage is fixed at seven days.');
        }

        if ($shipment->onFailReceiveType === OnFailReceiveType::PROCESS_AS_REFUSAL) {
            throw new UkrPostValidationException('Storage does not apply to PROCESS_AS_REFUSAL shipments.');
        }

        if ($days > 28) {
            throw new UkrPostValidationException('Storage cannot exceed 28 days.');
        }

        if ($shipment->returnAfterStorageDays !== null && $days <= $shipment->returnAfterStorageDays) {
            throw new UkrPostValidationException('Storage days can only be increased.');
        }

        $office = $this->recipientOffice($shipment->recipient);
        $type = self::officeType($office);
        if (in_array($type, ['PARTNER', 'PARCEL_TERMINAL', 'PUDO', 'П-т'], true)) {
            throw new UkrPostValidationException('Storage cannot be extended for a parcel terminal or PUDO.');
        }

        if ($type === 'ПВ' && $shipment->status === ShipmentStatus::IN_DEPARTMENT) {
            throw new UkrPostValidationException('Storage cannot be extended at a mobile office after arrival.');
        }

        $minimum = in_array($type, ['СВ', 'ПВ'], true) ? 15 : 8;
        if ($days < $minimum) {
            throw new UkrPostValidationException(sprintf('Storage must be between %d and 28 days for this office.', $minimum));
        }

        return ShipmentData::from($this->put(
            '/shipments/management/'.rawurlencode($shipmentUuid).'/return-after-storage-days/'.$days,
        )->all());
    }

    public function changeRecipient(string $shipmentUuid, string $newRecipientUuid): ShipmentData
    {
        $shipment = $this->shipment($shipmentUuid);
        self::ensureStatus($shipment, self::RECIPIENT_STATUSES, 'recipient change');
        if ($shipment->type === ShipmentType::INTERNATIONAL) {
            throw new UkrPostValidationException('International shipment recipient data cannot be changed.');
        }

        $recipient = $this->client($newRecipientUuid);
        self::validateRecipientReplacement($shipment, $recipient);

        return ShipmentData::from($this->put(
            '/shipments/management/'.rawurlencode($shipmentUuid).'/recipient',
            ['recipient' => ['uuid' => $newRecipientUuid]],
        )->all());
    }

    public function forward(string $shipmentUuid, string $newRecipientUuid, DeliveryType $deliveryType): ShipmentData
    {
        $shipment = $this->shipment($shipmentUuid);
        self::ensureStatus($shipment, self::FORWARD_AND_RETURN_STATUSES, 'forwarding');
        if (! in_array($shipment->type, [ShipmentType::EXPRESS, ShipmentType::STANDARD, ShipmentType::DOCUMENT, ShipmentType::INTERNATIONAL], true)) {
            throw new UkrPostValidationException('This shipment type cannot be forwarded.');
        }

        $recipient = $this->client($newRecipientUuid);
        self::validateForwardRecipient($shipment->recipient, $recipient);

        if ($shipment->personalHanding === true && ! $deliveryType->hasCourierDelivery()) {
            $office = $this->recipientOffice($recipient);
            if (self::officeIsRestricted($office)) {
                throw new UkrPostValidationException('Personal handing cannot be forwarded to this office.');
            }
        }

        return ShipmentData::from($this->put(
            '/shipments/management/'.rawurlencode($shipmentUuid).'/forward',
            ['recipient' => ['uuid' => $newRecipientUuid], 'deliveryType' => $deliveryType->value],
        )->all());
    }

    public function changePostPay(string $shipmentUuid, float $amount): ShipmentData
    {
        $shipment = $this->shipment($shipmentUuid);
        self::ensureStatus($shipment, self::RECIPIENT_STATUSES, 'postpay change');
        if (! in_array($shipment->type, self::POSTPAY_TYPES, true)) {
            throw new UkrPostValidationException('This shipment type cannot have postpay changed.');
        }

        $declaredPrice = $shipment->declaredPrice ?? self::parcelDeclaredPrice($shipment);
        ShipmentConstraints::validatePostPayChange($amount, $declaredPrice);
        self::ensureIndividualMiddleName($shipment->sender, 'sender');
        self::ensureIndividualMiddleName($shipment->recipient, 'recipient');

        if ($shipment->status !== ShipmentStatus::CREATED
            && $shipment->postPayPaidByRecipient === false
            && $amount > ($shipment->postPay ?? 0.0)) {
            throw new UkrPostValidationException('Sender-paid postpay cannot be increased after creation.');
        }

        return ShipmentData::from($this->put(
            '/shipments/management/'.rawurlencode($shipmentUuid).'/postpay',
            ['postPay' => $amount],
        )->all());
    }

    /** @return Collection<int, mixed> */
    public function createReturnOrder(string $barcode, string $senderToken, int $returnAddressId): Collection
    {
        $shipment = $this->shipmentByBarcode($barcode);
        self::ensureStatus($shipment, self::FORWARD_AND_RETURN_STATUSES, 'return order');

        if (! in_array($shipment->type, [ShipmentType::EXPRESS, ShipmentType::STANDARD, ShipmentType::DOCUMENT, ShipmentType::CARGO], true)) {
            throw new UkrPostValidationException('This shipment type cannot be returned by order.');
        }

        if (trim($senderToken) === '' || $returnAddressId < 1) {
            throw new UkrPostValidationException('A sender token and return address are required.');
        }

        return $this->post('/dispatch/return-order', [
            'barcode' => $barcode,
            'senderToken' => $senderToken,
            'returnAddressId' => (string) $returnAddressId,
        ]);
    }

    private function shipment(string $uuid): ShipmentData
    {
        return ShipmentData::from($this->get('/shipments/'.rawurlencode($uuid))->all());
    }

    private function shipmentByBarcode(string $barcode): ShipmentData
    {
        return ShipmentData::from($this->get('/shipments/barcode/'.rawurlencode($barcode))->all());
    }

    private function client(string $uuid): ClientData
    {
        return ClientData::from($this->get('/clients/'.rawurlencode($uuid))->all());
    }

    /** @return array<string, mixed> */
    private function recipientOffice(?ClientData $recipient): array
    {
        if ($recipient?->addressId === null) {
            throw new UkrPostValidationException('Recipient address is required to validate the destination office.');
        }

        $address = AddressData::from($this->get('/addresses/'.$recipient->addressId)->all());
        if ($address->postcode === null) {
            throw new UkrPostValidationException('Recipient postcode is required to validate the destination office.');
        }

        $office = AddressClassifier::make($this->credentials)
            ->postOfficesByPostindex(['pi' => $address->postcode])
            ->first();

        if (! is_array($office)) {
            throw new UkrPostValidationException('The destination office could not be resolved.');
        }

        return self::stringKeyed($office, 'destination office');
    }

    /** @param list<ShipmentStatus> $allowed */
    private static function ensureStatus(ShipmentData $shipment, array $allowed, string $operation): void
    {
        if ($shipment->status === null || ! in_array($shipment->status, $allowed, true)) {
            throw new UkrPostValidationException('Shipment status does not allow '.$operation.'.');
        }
    }

    private static function validateRecipientReplacement(ShipmentData $shipment, ClientData $new): void
    {
        self::ensureNamedRecipient($new, $shipment->postPay !== null && $shipment->postPay > 0);
        $old = $shipment->recipient;

        if ($old !== null && ($old->type !== $new->type || $old->addressId !== $new->addressId)) {
            throw new UkrPostValidationException('Only recipient name or phone may change.');
        }
    }

    private static function validateForwardRecipient(?ClientData $old, ClientData $new): void
    {
        self::ensureNamedRecipient($new, false);
        if ($old === null || $old->type !== $new->type) {
            throw new UkrPostValidationException('Forwarding cannot change recipient type.');
        }

        foreach (['firstName', 'lastName', 'middleName', 'name', 'phoneNumber'] as $field) {
            if ($old->{$field} !== null && $new->{$field} !== $old->{$field}) {
                throw new UkrPostValidationException('Forwarding may change only the recipient address.');
            }
        }
    }

    private static function ensureNamedRecipient(ClientData $client, bool $middleNameRequired): void
    {
        if ($client->type === ClientType::INDIVIDUAL
            && (! self::filled($client->firstName) || ! self::filled($client->lastName))) {
            throw new UkrPostValidationException('Recipient firstName and lastName are required.');
        }

        if ($middleNameRequired) {
            self::ensureIndividualMiddleName($client, 'recipient');
        }
    }

    private static function ensureIndividualMiddleName(?ClientData $client, string $role): void
    {
        if ($client?->type === ClientType::INDIVIDUAL && ! self::filled($client->middleName)) {
            throw new UkrPostValidationException($role.' middleName is required to change postpay.');
        }
    }

    private static function parcelDeclaredPrice(ShipmentData $shipment): float
    {
        return array_reduce(
            $shipment->parcels,
            static fn (float $sum, ParcelData $parcel): float => $sum + ($parcel->declaredPrice ?? 0.0),
            0.0,
        );
    }

    /** @param array<string, mixed> $office */
    private static function officeIsRestricted(array $office): bool
    {
        return in_array(self::officeType($office), ['PARTNER', 'PARCEL_TERMINAL', 'PUDO', 'П-т'], true)
            || in_array($office['RESTRICTED_ACCESS'] ?? null, [1, '1', true], true);
    }

    /** @param array<string, mixed> $office */
    private static function officeType(array $office): ?string
    {
        $type = $office['TYPE_SHORT'] ?? null;

        return is_string($type) ? $type : null;
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<string, mixed>
     */
    private static function stringKeyed(array $value, string $context): array
    {
        $result = [];
        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw new UkrPostValidationException('Unexpected '.$context.' response.');
            }

            $result[$key] = $item;
        }

        return $result;
    }

    private static function filled(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }
}
