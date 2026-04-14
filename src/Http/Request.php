<?php

declare(strict_types=1);

namespace Scafera\Kernel\Http;

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

final class Request
{
    public readonly ParameterBag $query;
    public readonly ParameterBag $request;
    public readonly HeaderBag $headers;

    /** @internal Constructed by the kernel's argument resolver — not for userland instantiation. */
    public function __construct(private readonly SymfonyRequest $inner)
    {
        $this->query = new ParameterBag($inner->query->all());
        $this->request = new ParameterBag($inner->request->all());
        $this->headers = new HeaderBag($inner->headers->all());
    }

    public function getMethod(): string
    {
        return $this->inner->getMethod();
    }

    public function isMethod(string $method): bool
    {
        return $this->inner->isMethod($method);
    }

    public function getPath(): string
    {
        return $this->inner->getPathInfo();
    }

    public function getUri(): string
    {
        return $this->inner->getUri();
    }

    public function getContent(): string
    {
        return $this->inner->getContent();
    }

    /** @return array<string, mixed> */
    public function json(): array
    {
        return $this->inner->toArray();
    }

    public function routeParam(string $key, mixed $default = null): mixed
    {
        $params = $this->inner->attributes->get('_route_params', []);

        return $params[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function routeParams(): array
    {
        return $this->inner->attributes->get('_route_params', []);
    }

    /** @internal For use by Scafera capability packages only. Not part of the public API. */
    public function getSymfonyRequest(): SymfonyRequest
    {
        return $this->inner;
    }
}
