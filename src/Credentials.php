<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi;

use Sashalenz\UkrPostApi\Exceptions\UkrPostAuthenticationException;

final readonly class Credentials
{
    public function __construct(
        public string $bearerEcom,
        public string $counterpartyToken,
        public ?string $bearerStatusTracking = null,
        public ?string $counterpartyUuid = null,
        public bool $sandbox = false,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            bearerEcom: self::stringConfig('ukrpost-api.credentials.bearer_ecom'),
            counterpartyToken: self::stringConfig('ukrpost-api.credentials.counterparty_token'),
            bearerStatusTracking: self::nullableStringConfig('ukrpost-api.credentials.bearer_status_tracking'),
            counterpartyUuid: self::nullableStringConfig('ukrpost-api.credentials.counterparty_uuid'),
            sandbox: (bool) config('ukrpost-api.sandbox', false),
        );
    }

    public function baseUrl(Endpoint $endpoint): string
    {
        return self::stringConfig(sprintf('ukrpost-api.urls.%s.%s', $this->sandbox ? 'sandbox' : 'production', $endpoint->value));
    }

    public function bearerFor(Endpoint $endpoint): string
    {
        return match ($endpoint) {
            Endpoint::STATUS_TRACKING => $this->bearerStatusTracking
                ?? throw new UkrPostAuthenticationException('StatusTracking bearer is not configured'),
            default => $this->bearerEcom,
        };
    }

    private static function stringConfig(string $key): string
    {
        $value = config($key, '');

        return is_string($value) ? $value : '';
    }

    private static function nullableStringConfig(string $key): ?string
    {
        $value = config($key);

        return is_string($value) ? $value : null;
    }
}
