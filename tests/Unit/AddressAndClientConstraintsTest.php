<?php

declare(strict_types=1);

use Sashalenz\UkrPostApi\Exceptions\UkrPostValidationException;
use Sashalenz\UkrPostApi\Support\AddressConstraints;
use Sashalenz\UkrPostApi\Support\ClientConstraints;

it('validates address postcode, floor, and mailbox before HTTP', function (): void {
    AddressConstraints::validate(['postcode' => '01001', 'mailbox' => '12', 'floor' => 57]);

    expect(fn () => AddressConstraints::validate(['postcode' => '1001']))
        ->toThrow(UkrPostValidationException::class)
        ->and(fn () => AddressConstraints::validate(['postcode' => '01001', 'floor' => 58]))
        ->toThrow(UkrPostValidationException::class)
        ->and(fn () => AddressConstraints::validate(['postcode' => '01001', 'mailbox' => '12', 'street' => 'Хрещатик']))
        ->toThrow(UkrPostValidationException::class);
});

it('validates company and entrepreneur tax identifiers', function (): void {
    ClientConstraints::validateForCreate(['type' => 'COMPANY', 'name' => 'ТОВ Приклад', 'edrpou' => '32855961']);
    ClientConstraints::validateForCreate(['type' => 'PRIVATE_ENTREPRENEUR', 'name' => 'ФОП Приклад', 'tin' => '3000607991']);

    expect(fn () => ClientConstraints::validateForCreate(['type' => 'COMPANY', 'name' => 'ТОВ Приклад', 'edrpou' => '32855962']))
        ->toThrow(UkrPostValidationException::class)
        ->and(fn () => ClientConstraints::validateForCreate(['type' => 'PRIVATE_ENTREPRENEUR', 'name' => 'ФОП Приклад', 'tin' => '3000607994']))
        ->toThrow(UkrPostValidationException::class);
});

it('validates individual names, phone, IBAN, and immutable type', function (): void {
    ClientConstraints::validateForCreate([
        'type' => 'INDIVIDUAL',
        'firstName' => 'Іван',
        'lastName' => 'Петренко',
        'phoneNumber' => '0501234567',
        'bankAccount' => 'UA123456789012345678901234567',
    ]);

    expect(fn () => ClientConstraints::validateForCreate(['type' => 'INDIVIDUAL', 'firstName' => 'І', 'lastName' => 'Петренко']))
        ->toThrow(UkrPostValidationException::class)
        ->and(fn () => ClientConstraints::validateForUpdate(['type' => 'COMPANY']))
        ->toThrow(UkrPostValidationException::class);
});
