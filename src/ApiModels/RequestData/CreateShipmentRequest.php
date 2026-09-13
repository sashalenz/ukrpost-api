<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels\RequestData;

use Sashalenz\UkrPostApi\Enums\DeliveryType;
use Sashalenz\UkrPostApi\Enums\OnFailReceiveType;
use Sashalenz\UkrPostApi\Enums\ShipmentType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class CreateShipmentRequest extends Data
{
    /** @param array<int, ParcelRequestData|array<string, mixed>> $parcels */
    public function __construct(
        public string $senderUuid,
        public string $recipientUuid,
        public DeliveryType $deliveryType,
        public array $parcels,
        public ShipmentType $type = ShipmentType::EXPRESS,
        public bool $paidByRecipient = false,
        public float|null|Optional $postPay = new Optional,
        public int|null|Optional $senderAddressId = new Optional,
        public int|null|Optional $recipientAddressId = new Optional,
        public int|null|Optional $returnAddressId = new Optional,
        public string|null|Optional $description = new Optional,
        public string|null|Optional $recipientPhone = new Optional,
        public string|null|Optional $recipientEmail = new Optional,
        public OnFailReceiveType|null|Optional $onFailReceiveType = new Optional,
        public int|null|Optional $returnAfterStorageDays = new Optional,
        public bool $postPayPaidByRecipient = true,
        public bool $transferPostPayToBankAccount = false,
        public string|null|Optional $postPayRecipientUuid = new Optional,
        public bool $checkOnDelivery = false,
        public bool $documentBack = false,
        public bool $fragile = false,
        public bool $packedBySender = false,
        public bool $partialReceiptAllowed = false,
        public bool $personalHanding = false,
        public bool $listOfEnclosedItems = false,
        public bool $sms = true,
        public bool $withDeliveryNotification = false,
        public bool $withEmailDeliveryNotification = false,
        public bool $ascentToTheFloor = false,
    ) {}

    /** @return array<string, mixed> */
    public function toApiArray(): array
    {
        $parcels = array_map(
            static fn (ParcelRequestData|array $parcel): array => $parcel instanceof ParcelRequestData ? $parcel->toApiArray() : $parcel,
            $this->parcels,
        );

        return array_filter([
            'sender' => ['uuid' => $this->senderUuid],
            'recipient' => ['uuid' => $this->recipientUuid],
            'deliveryType' => $this->deliveryType->value,
            'type' => $this->type->value,
            'parcels' => $parcels,
            'paidByRecipient' => $this->paidByRecipient,
            'postPay' => $this->postPay,
            'senderAddressId' => $this->senderAddressId,
            'recipientAddressId' => $this->recipientAddressId,
            'returnAddressId' => $this->returnAddressId,
            'description' => $this->description,
            'recipientPhone' => $this->recipientPhone,
            'recipientEmail' => $this->recipientEmail,
            'onFailReceiveType' => $this->onFailReceiveType instanceof OnFailReceiveType ? $this->onFailReceiveType->value : $this->onFailReceiveType,
            'returnAfterStorageDays' => $this->returnAfterStorageDays,
            'postPayPaidByRecipient' => $this->postPayPaidByRecipient,
            'transferPostPayToBankAccount' => $this->transferPostPayToBankAccount,
            'postPayRecipientUuid' => $this->postPayRecipientUuid,
            'checkOnDelivery' => $this->checkOnDelivery,
            'documentBack' => $this->documentBack,
            'fragile' => $this->fragile,
            'packedBySender' => $this->packedBySender,
            'partialReceiptAllowed' => $this->partialReceiptAllowed,
            'personalHanding' => $this->personalHanding,
            'listOfEnclosedItems' => $this->listOfEnclosedItems,
            'sms' => $this->sms,
            'withDeliveryNotification' => $this->withDeliveryNotification,
            'withEmailDeliveryNotification' => $this->withEmailDeliveryNotification,
            'ascentToTheFloor' => $this->ascentToTheFloor,
        ], static fn (mixed $value): bool => ! $value instanceof Optional);
    }
}
