<?php

declare(strict_types=1);

namespace Scafera\Kernel\Http\Internal;

use Scafera\Kernel\Http\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * @internal Injects Scafera\Http\Request when a controller type-hints it.
 */
final class RequestResolver implements ValueResolverInterface
{
    public function resolve(SymfonyRequest $request, ArgumentMetadata $argument): iterable
    {
        if ($argument->getType() !== Request::class) {
            return [];
        }

        yield new Request($request);
    }
}
