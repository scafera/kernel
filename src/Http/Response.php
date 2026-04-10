<?php

declare(strict_types=1);

namespace Scafera\Kernel\Http;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class Response implements ResponseInterface
{
    private readonly SymfonyResponse $symfonyResponse;

    public function __construct(string $content = '', int $status = 200, array $headers = [])
    {
        $this->symfonyResponse = new SymfonyResponse($content, $status, $headers);
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
