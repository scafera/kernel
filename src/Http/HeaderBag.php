<?php

declare(strict_types=1);

namespace Scafera\Kernel\Http;

final class HeaderBag
{
    /** @param array<string, list<string|null>> $headers */
    public function __construct(private readonly array $headers = [])
    {
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $key = strtolower($key);

        return $this->headers[$key][0] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($this->headers[strtolower($key)]);
    }

    /** @return array<string, list<string|null>> */
    public function all(): array
    {
        return $this->headers;
    }
}
