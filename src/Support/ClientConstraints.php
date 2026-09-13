<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\Support;

use Sashalenz\UkrPostApi\Enums\ClientType;
use Sashalenz\UkrPostApi\Exceptions\UkrPostValidationException;

final class ClientConstraints
{
    /** @param array<string, mixed> $data */
    public static function validateForCreate(array $data): void
    {
        $type = self::type($data['type'] ?? ClientType::COMPANY->value);

        if ($type === ClientType::INDIVIDUAL) {
            self::requireName($data, 'firstName');
            self::requireName($data, 'lastName');
        }

        if ($type === ClientType::COMPANY) {
            self::requireName($data, 'name');
            self::validateEdrpou($data['edrpou'] ?? null, true);
        }

        if ($type === ClientType::PRIVATE_ENTREPRENEUR) {
            self::requireName($data, 'name');
            self::validateTin($data['tin'] ?? null, true);
        }

        self::validateCommon($data);
    }

    /** @param array<string, mixed> $data */
    public static function validateForUpdate(array $data): void
    {
        if (array_key_exists('type', $data)) {
            throw new UkrPostValidationException('Client type cannot be changed after creation.');
        }

        self::validateCommon($data);
    }

    /** @param array<string, mixed> $data */
    private static function validateCommon(array $data): void
    {
        self::validateEdrpou($data['edrpou'] ?? null);
        self::validateTin($data['tin'] ?? null);

        if (isset($data['phoneNumber']) && (! is_string($data['phoneNumber']) || ! preg_match('/^\d{1,25}$/', $data['phoneNumber']))) {
            throw new UkrPostValidationException('phoneNumber must contain no more than 25 digits.');
        }

        if (isset($data['bankAccount']) && (! is_string($data['bankAccount']) || ! preg_match('/^UA\d{27}$/', $data['bankAccount']))) {
            throw new UkrPostValidationException('bankAccount must be a 29-character Ukrainian IBAN.');
        }
    }

    private static function validateEdrpou(mixed $value, bool $required = false): void
    {
        if ($value === null && ! $required) {
            return;
        }

        if (! is_string($value) || ! EdrpouValidator::isValid($value)) {
            throw new UkrPostValidationException('edrpou must be a valid 5-8 digit code.');
        }
    }

    private static function validateTin(mixed $value, bool $required = false): void
    {
        if ($value === null && ! $required) {
            return;
        }

        if (! is_string($value) || ! TinValidator::isValid($value)) {
            throw new UkrPostValidationException('tin must be a valid 10 digit RNOKPP.');
        }
    }

    /** @param array<string, mixed> $data */
    private static function requireName(array $data, string $field): void
    {
        $value = $data[$field] ?? null;

        if (! is_string($value) || mb_strlen(trim($value)) < 2) {
            throw new UkrPostValidationException($field.' must contain at least two characters.');
        }
    }

    private static function type(mixed $value): ClientType
    {
        $type = $value instanceof ClientType ? $value : (is_string($value) ? ClientType::tryFrom($value) : null);

        return $type ?? throw new UkrPostValidationException('Invalid client type.');
    }
}
