<?php

declare(strict_types=1);

namespace Scafera\Kernel\Contract;

interface AdvisorInterface
{
    public function getName(): string;

    /** @return string|null null = ready, string = skip reason */
    public function skipped(string $projectDir): ?string;

    /**
     * @return list<string> List of advisory messages (empty = nothing to report).
     */
    public function advise(string $projectDir): array;
}
