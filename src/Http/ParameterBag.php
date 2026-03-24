<?php

declare(strict_types=1);

namespace Scafera\Kernel\Http;

final class ParameterBag
{
    /** @param array<string, mixed> $parameters */
    public function __construct(private readonly array $parameters = [])
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->parameters[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->parameters);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->parameters;
    }

    public function getInt(string $key, int $default = 0): int
    {
        return (int) ($this->parameters[$key] ?? $default);
    }

    public function getString(string $key, string $default = ''): string
    {
        return (string) ($this->parameters[$key] ?? $default);
    }

    public function getBoolean(string $key, bool $default = false): bool
    {
        return filter_var($this->parameters[$key] ?? $default, \FILTER_VALIDATE_BOOLEAN);
    }
}
