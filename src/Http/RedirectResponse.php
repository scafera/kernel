<?php

declare(strict_types=1);

namespace Scafera\Kernel\Http;

use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

final class RedirectResponse implements ResponseInterface
{
    private readonly SymfonyRedirectResponse $symfonyResponse;

    public function __construct(string $url, int $status = 302, array $headers = [])
    {
        $this->symfonyResponse = new SymfonyRedirectResponse($url, $status, $headers);
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
