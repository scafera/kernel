<?php

declare(strict_types=1);

namespace Scafera\Kernel\Contract;

final class AdvisorStatus
{
    private function __construct(
        public readonly bool $ready,
        public readonly string $reason,
    ) {
    }

    public static function ready(): self
    {
        return new self(true, '');
    }

    public static function skipped(string $reason): self
    {
        return new self(false, $reason);
    }
}
