<?php

declare(strict_types=1);

namespace Scafera\Kernel\Http;

interface ResponseInterface
{
    public function getStatusCode(): int;

    public function getContent(): string;

    /** @return array<string, list<string|null>> */
    public function getHeaders(): array;
}
