<?php

declare(strict_types=1);

namespace Scafera\Kernel\Http;

use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

final class RedirectResponse extends Response
{
    public function __construct(string $url, int $status = 302, array $headers = [])
    {
        $this->symfonyResponse = new SymfonyRedirectResponse($url, $status, $headers);
    }
}
