<?php

declare(strict_types=1);

namespace Scafera\Kernel\Console\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsCommand
{
    public function __construct(
        public readonly string $name,
        public readonly string $description = '',
    ) {
    }
}
