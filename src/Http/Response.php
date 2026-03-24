<?php

declare(strict_types=1);

namespace Scafera\Kernel\Http;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class Response
{
    /** @internal */
    protected SymfonyResponse $symfonyResponse;

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

    /** @internal Used by the kernel to convert to a Symfony response. */
    public function toSymfonyResponse(): SymfonyResponse
    {
        return $this->symfonyResponse;
    }
}
