<?php

declare(strict_types=1);

namespace Scafera\Kernel\Http\Internal;

use Scafera\Kernel\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * @internal Converts a Scafera response to a Symfony response for kernel dispatch.
 *
 * This class exists to enforce the boundary between Scafera's HTTP abstraction
 * and Symfony's internals. Userland code cannot access Symfony responses directly
 * because Response has no escape hatch method.
 *
 * The conversion is explicit: we construct a new Symfony response from the
 * public getters (content, status, headers) rather than accessing internals.
 */
final class ResponseConverter
{
    public static function toSymfony(Response $response): SymfonyResponse
    {
        return new SymfonyResponse(
            $response->getContent(),
            $response->getStatusCode(),
            $response->getHeaders(),
        );
    }
}
