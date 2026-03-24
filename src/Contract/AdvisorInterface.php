<?php

declare(strict_types=1);

namespace Scafera\Kernel\Contract;

interface AdvisorInterface
{
    public function getName(): string;

    public function canRun(string $projectDir): AdvisorStatus;

    /**
     * @return list<string> List of advisory messages (empty = nothing to report).
     */
    public function advise(string $projectDir): array;
}
