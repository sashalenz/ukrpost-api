<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels;

use Illuminate\Support\Collection;
use Sashalenz\UkrPostApi\Credentials;
use Sashalenz\UkrPostApi\Endpoint;
use Sashalenz\UkrPostApi\Request;

/** @phpstan-consistent-constructor */
abstract class BaseModel
{
    protected Endpoint $endpoint = Endpoint::ECOM;

    private bool $canBeCached = false;

    private int $cacheSeconds = -1;

    public function __construct(protected readonly Credentials $credentials) {}

    /** @phpstan-consistent-constructor */
    public static function make(?Credentials $credentials = null): static
    {
        return new static($credentials ?? Credentials::fromConfig());
    }

    public function cache(int $seconds = -1): static
    {
        $this->canBeCached = true;
        $this->cacheSeconds = $seconds;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return Collection<int, mixed>
     */
    protected function get(string $path, array $query = []): Collection
    {
        return $this->request('GET', $path, null, $query);
    }

    /**
     * @param  array<array-key, mixed>|null  $payload
     * @param  array<string, mixed>  $query
     * @return Collection<int, mixed>
     */
    protected function post(string $path, ?array $payload = null, array $query = []): Collection
    {
        return $this->request('POST', $path, $payload, $query);
    }

    /**
     * @param  array<array-key, mixed>|null  $payload
     * @return Collection<int, mixed>
     */
    protected function put(string $path, ?array $payload = null): Collection
    {
        return $this->request('PUT', $path, $payload);
    }

    /** @return Collection<int, mixed> */
    protected function delete(string $path): Collection
    {
        return $this->request('DELETE', $path);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<array-key, mixed>|null  $payload
     */
    protected function binary(string $path, array $query = [], string $method = 'GET', ?array $payload = null): string
    {
        $result = new Request($this->credentials, $this->endpoint, $method, $path, $payload, $query, true)->make();

        return is_string($result) ? $result : $result->toJson();
    }

    /**
     * @param  array<array-key, mixed>|null  $payload
     * @param  array<string, mixed>  $query
     * @return Collection<int, mixed>
     */
    private function request(string $method, string $path, ?array $payload = null, array $query = []): Collection
    {
        $request = new Request($this->credentials, $this->endpoint, $method, $path, $payload, $query);
        $result = $this->canBeCached ? $request->cache($this->cacheSeconds) : $request->make();

        return $result instanceof Collection ? $result : collect([$result]);
    }
}
