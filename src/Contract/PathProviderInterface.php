<?php

declare(strict_types=1);

namespace Scafera\Kernel\Contract;

interface PathProviderInterface
{
    /** @return list<array{label: string, path: string}> */
    public function getPaths(): array;

    public function getSourceName(): string;
}
