<?php

declare(strict_types=1);

namespace Scafera\Kernel\Contract;

interface ViewInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function render(string $template, array $context = []): string;
}
