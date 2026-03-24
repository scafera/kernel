<?php

declare(strict_types=1);

namespace Scafera\Kernel\Http;

use Symfony\Component\HttpFoundation\JsonResponse as SymfonyJsonResponse;

final class JsonResponse extends Response
{
    /** @param array<string, mixed>|list<mixed>|scalar|null $data */
    public function __construct(mixed $data = null, int $status = 200, array $headers = [])
    {
        $this->symfonyResponse = new SymfonyJsonResponse($data, $status, $headers);
    }
}
