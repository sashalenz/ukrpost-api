<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels;

use Illuminate\Support\Collection;
use Sashalenz\UkrPostApi\Endpoint;

final class AddressClassifier extends BaseModel
{
    protected Endpoint $endpoint = Endpoint::CLASSIFIER;

    /** @return Collection<int, mixed> */
    public function regions(?string $regionName = null, ?string $regionNameEn = null): Collection
    {
        return $this->entries('get_regions_by_region_ua', array_filter([
            'region_name' => $regionName,
            'region_name_en' => $regionNameEn,
        ], static fn (mixed $value): bool => $value !== null));
    }

    /** @return Collection<int, mixed> */
    public function districts(string $regionId, ?string $districtUa = null): Collection
    {
        return $this->entries('get_districts_by_region_id_and_district_ua', array_filter(['region_id' => $regionId, 'district_ua' => $districtUa], static fn (mixed $value): bool => $value !== null));
    }

    /** @return Collection<int, mixed> */
    public function cities(string $regionId, string $districtId, ?string $cityUa = null, ?string $koatuu = null): Collection
    {
        return $this->entries('get_city_by_region_id_and_district_id_and_city_ua', array_filter(['region_id' => $regionId, 'district_id' => $districtId, 'city_ua' => $cityUa, 'koatuu' => $koatuu], static fn (mixed $value): bool => $value !== null));
    }

    /** @return Collection<int, mixed> */
    public function streets(string $regionId, string $districtId, string $cityId, ?string $streetUa = null): Collection
    {
        return $this->entries('get_street_by_region_id_and_district_id_and_city_id_and_street_ua', array_filter(['region_id' => $regionId, 'district_id' => $districtId, 'city_id' => $cityId, 'street_ua' => $streetUa], static fn (mixed $value): bool => $value !== null));
    }

    /** @return Collection<int, mixed> */
    public function houses(string $streetId, string $houseNumber): Collection
    {
        return $this->entries('get_addr_house_by_street_id', ['street_id' => $streetId, 'housenumber' => $houseNumber]);
    }

    /** @return Collection<int, mixed> */
    public function courierAreaByPostindex(string $postindex): Collection
    {
        return $this->entries('get_courierarea_by_postindex', ['postindex' => $postindex]);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return Collection<int, mixed>
     */
    public function postOfficesByPostindex(array $parameters): Collection
    {
        return $this->entries('get_postoffices_by_postindex', $parameters);
    }

    /** @return Collection<int, mixed> */
    public function postOfficesByCityId(string $cityId, ?string $districtId = null, ?string $regionId = null, ?string $postindex = null): Collection
    {
        return $this->entries('get_postoffices_by_city_id', array_filter(['city_id' => $cityId, 'district_id' => $districtId, 'region_id' => $regionId, 'postindex' => $postindex], static fn (mixed $value): bool => $value !== null));
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return Collection<int, mixed>
     */
    public function postOfficesByCityIdentifier(array $parameters): Collection
    {
        return $this->entries('get_postoffices_by_postcode_cityid_cityvpzid', $parameters);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return Collection<int, mixed>
     */
    public function postOfficesByKoatuu(array $parameters): Collection
    {
        return $this->postOfficesByCityIdentifier($parameters);
    }

    /** @return Collection<int, mixed> */
    public function postOfficesByGeolocation(float $lat, float $long, float|int $maxdistance): Collection
    {
        return $this->entries('get_postoffices_by_geolocation', ['lat' => $lat, 'long' => $long, 'maxdistance' => $maxdistance]);
    }

    /** @return Collection<int, mixed> */
    public function openHoursByPostindex(string $pc, string $id): Collection
    {
        return $this->entries('get_postoffices_openhours_by_postindex', ['pc' => $pc, 'id' => $id]);
    }

    /** @return Collection<int, mixed> */
    public function openHoursById(string $pc, string $id): Collection
    {
        return $this->entries('get_postoffices_openhours_by_id', ['pc' => $pc, 'id' => $id]);
    }

    /** @return Collection<int, mixed> */
    public function mobileOpenHoursByPostindex(string $techindex): Collection
    {
        return $this->entries('get_postoffices_mobile_openhours_by_postindex', ['techindex' => $techindex]);
    }

    /** @return Collection<int, mixed> */
    public function cityByPostcode(string $postcode, ?string $lang = null): Collection
    {
        return $this->entries('get_city_details_by_postcode', array_filter(['postcode' => $postcode, 'lang' => $lang], static fn (mixed $value): bool => $value !== null));
    }

    /** @return Collection<int, mixed> */
    public function addressByPostcode(string $postcode, ?string $lang = null): Collection
    {
        return $this->entries('get_address_by_postcode', array_filter(['postcode' => $postcode, 'lang' => $lang], static fn (mixed $value): bool => $value !== null));
    }

    /** @return Collection<int, mixed> */
    public function postcodesByCityId(string $cityId): Collection
    {
        return $this->entries('get_postcode_by_city_id', ['city_id' => $cityId]);
    }

    /** @return Collection<int, mixed> */
    public function districtByName(string $regionId, string $districtName, ?string $lang = null): Collection
    {
        return $this->entries('get_district_by_name', array_filter(['region_id' => $regionId, 'district_name' => $districtName, 'lang' => $lang], static fn (mixed $value): bool => $value !== null));
    }

    /** @return Collection<int, mixed> */
    public function cityByName(string $regionId, string $districtId, string $cityName, ?string $lang = null): Collection
    {
        return $this->entries('get_city_by_name', array_filter(['region_id' => $regionId, 'district_id' => $districtId, 'city_name' => $cityName, 'lang' => $lang], static fn (mixed $value): bool => $value !== null));
    }

    /** @return Collection<int, mixed> */
    public function streetByName(string $cityId, string $streetName, ?string $lang = null): Collection
    {
        return $this->entries('get_street_by_name', array_filter(['city_id' => $cityId, 'street_name' => $streetName, 'lang' => $lang], static fn (mixed $value): bool => $value !== null));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return Collection<int, mixed>
     */
    private function entries(string $path, array $query): Collection
    {
        $ttl = config('ukrpost-api.cache_ttl', 3600);
        $response = $this->cache(is_int($ttl) ? $ttl : 3600)->get($path, $query);
        $entries = $response->get('Entries', []);

        if (! is_array($entries)) {
            return collect();
        }

        $items = $entries['Entry'] ?? [];

        return is_array($items) ? collect($items) : collect([$items]);
    }
}
