<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi;

use Sashalenz\UkrPostApi\ApiModels\Address\Address;
use Sashalenz\UkrPostApi\ApiModels\AddressClassifier;
use Sashalenz\UkrPostApi\ApiModels\BaseModel;
use Sashalenz\UkrPostApi\ApiModels\Calculation;
use Sashalenz\UkrPostApi\ApiModels\Client\Client;
use Sashalenz\UkrPostApi\ApiModels\Documents;
use Sashalenz\UkrPostApi\ApiModels\Shipment\Shipment;
use Sashalenz\UkrPostApi\ApiModels\ShipmentGroup\ShipmentGroup;
use Sashalenz\UkrPostApi\ApiModels\StatusTracking\StatusTracking;
use Sashalenz\UkrPostApi\ApiModels\Transfer;

final class UkrPostApi
{
    public static function addresses(?Credentials $credentials = null): Address
    {
        return Address::make($credentials);
    }

    public static function clients(?Credentials $credentials = null): Client
    {
        return Client::make($credentials);
    }

    public static function shipments(?Credentials $credentials = null): Shipment
    {
        return Shipment::make($credentials);
    }

    public static function classifier(?Credentials $credentials = null): AddressClassifier
    {
        return AddressClassifier::make($credentials);
    }

    public static function statusTracking(?Credentials $credentials = null): StatusTracking
    {
        return StatusTracking::make($credentials);
    }

    public static function groups(?Credentials $credentials = null): ShipmentGroup
    {
        return ShipmentGroup::make($credentials);
    }

    public static function documents(?Credentials $credentials = null): Documents
    {
        return Documents::make($credentials);
    }

    public static function calculation(?Credentials $credentials = null): Calculation
    {
        return Calculation::make($credentials);
    }

    public static function transfers(?Credentials $credentials = null): Transfer
    {
        return Transfer::make($credentials);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @param  array<string, mixed>  $query
     */
    public static function request(Credentials $credentials, Endpoint $endpoint, string $method, string $path, ?array $payload = null, array $query = [], bool $binary = false): Request
    {
        return new Request($credentials, $endpoint, $method, $path, $payload, $query, $binary);
    }

    /** @param class-string<BaseModel> $model */
    public static function model(string $model, ?Credentials $credentials = null): BaseModel
    {
        return $model::make($credentials);
    }
}
