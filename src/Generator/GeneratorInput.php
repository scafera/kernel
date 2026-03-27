<?php

declare(strict_types=1);

namespace Scafera\Kernel\Generator;

final class GeneratorInput
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly bool $required = true,
        public readonly ?string $default = null,
    ) {
    }
}
