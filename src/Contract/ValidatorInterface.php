<?php

declare(strict_types=1);

namespace Scafera\Kernel\Contract;

interface ValidatorInterface
{
    public function getName(): string;

    /**
     * @return list<string> List of violation messages (empty = passed).
     */
    public function validate(string $projectDir): array;
}
