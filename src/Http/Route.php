<?php

declare(strict_types=1);

namespace Scafera\Kernel\Http;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class Route
{
    /** @var list<string> */
    public readonly array $normalizedMethods;

    /**
     * @param string|list<string> $methods HTTP method(s): 'GET', ['GET', 'POST'], etc.
     * @param array<string, string> $requirements Regex requirements for route parameters.
     * @param array<string, mixed> $defaults Default values for route parameters.
     */
    public function __construct(
        public readonly string $path,
        string|array $methods = [],
        public readonly string $name = '',
        public readonly array $requirements = [],
        public readonly array $defaults = [],
    ) {
        $this->normalizedMethods = array_map(
            'strtoupper',
            is_string($methods) ? ($methods === '' ? [] : [$methods]) : $methods,
        );
    }
}
