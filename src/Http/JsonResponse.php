<?php

declare(strict_types=1);

namespace Scafera\Kernel\Http;

use Symfony\Component\HttpFoundation\JsonResponse as SymfonyJsonResponse;

final class JsonResponse implements ResponseInterface
{
    private readonly SymfonyJsonResponse $symfonyResponse;

    /** @param array<string, mixed>|list<mixed>|scalar|null $data */
    public function __construct(mixed $data = null, int $status = 200, array $headers = [])
    {
        $this->symfonyResponse = new SymfonyJsonResponse($data, $status, $headers);
    }

    public function getStatusCode(): int
    {
        return $this->symfonyResponse->getStatusCode();
    }

    public function getContent(): string
    {
        return (string) $this->symfonyResponse->getContent();
    }

    /** @return array<string, list<string|null>> */
    public function getHeaders(): array
    {
        return $this->symfonyResponse->headers->all();
    }
}
