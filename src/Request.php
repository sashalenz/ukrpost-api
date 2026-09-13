<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Sashalenz\UkrPostApi\Exceptions\UkrPostApiUnavailableException;

final readonly class Request
{
    private const IDEMPOTENT_METHODS = ['GET', 'HEAD'];

    public function __construct(
        private Credentials $credentials,
        private Endpoint $endpoint,
        private string $httpMethod,
        private string $path,
        /** @var array<array-key, mixed>|null */
        private ?array $payload = null,
        /** @var array<string, mixed> */
        private array $query = [],
        private bool $binary = false,
    ) {}

    /** @return Collection<array-key, mixed>|string */
    public function make(): Collection|string
    {
        $response = $this->send();

        return $this->binary ? $response->body() : $response->collect();
    }

    /** @return Collection<array-key, mixed>|string */
    public function cache(int $seconds = -1): Collection|string
    {
        // Shipment state must be fresh before mutation; caching writes could also suppress real operations.
        if ($this->endpoint !== Endpoint::CLASSIFIER || ! in_array(strtoupper($this->httpMethod), self::IDEMPOTENT_METHODS, true)) {
            return $this->make();
        }

        return $seconds === -1
            ? Cache::rememberForever($this->cacheKey(), fn (): Collection|string => $this->make())
            : Cache::remember($this->cacheKey(), $seconds, fn (): Collection|string => $this->make());
    }

    private function send(): Response
    {
        $pending = Http::timeout($this->configInt('ukrpost-api.timeout', 15))
            ->baseUrl($this->credentials->baseUrl($this->endpoint))
            ->withToken($this->credentials->bearerFor($this->endpoint))
            ->acceptJson()
            ->withQueryParameters($this->queryParameters());

        // POST /shipments has real side effects and no API deduplication, so retrying it can create a paid duplicate.
        if (in_array(strtoupper($this->httpMethod), self::IDEMPOTENT_METHODS, true)) {
            $pending = $pending->retry(
                $this->configInt('ukrpost-api.retry_times', 3),
                $this->configInt('ukrpost-api.retry_sleep', 200),
                throw: false,
            );
        }

        try {
            $response = $pending->send($this->httpMethod, $this->path, ['json' => $this->payload]);
        } catch (ConnectionException $exception) {
            throw new UkrPostApiUnavailableException('Ukrposhta API unreachable.', previous: $exception);
        }

        if ($response->successful()) {
            return $response;
        }

        $code = $response->json('code');
        $message = $response->json('message') ?? $response->body();

        throw ErrorFactory::make($response->status(), is_string($code) ? $code : null, is_string($message) ? $message : $response->body());
    }

    /** @return array<string, mixed> */
    private function queryParameters(): array
    {
        // The API's sole eCom exception is POST /addresses; keeping it here prevents models from drifting apart on auth rules.
        $requiresToken = $this->endpoint->requiresCounterpartyToken()
            && ! ($this->endpoint === Endpoint::ECOM && strtoupper($this->httpMethod) === 'POST' && trim($this->path, '/') === 'addresses');

        return $requiresToken
            ? [...$this->query, 'token' => $this->credentials->counterpartyToken]
            : $this->query;
    }

    private function configInt(string $key, int $default): int
    {
        $value = config($key, $default);

        return is_int($value) ? $value : $default;
    }

    private function cacheKey(): string
    {
        return 'ukrpost-api:'.hash('sha256', serialize([
            $this->credentials->baseUrl($this->endpoint),
            $this->credentials->bearerFor($this->endpoint),
            $this->credentials->counterpartyToken,
            $this->endpoint->value,
            strtoupper($this->httpMethod),
            $this->path,
            $this->payload,
            $this->query,
            $this->binary,
        ]));
    }
}
