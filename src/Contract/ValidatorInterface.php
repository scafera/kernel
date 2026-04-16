<?php

declare(strict_types=1);

namespace Scafera\Kernel\Contract;

interface ValidatorInterface
{
    /** Stable machine-readable rule ID, format <package>.<rule-slug> (e.g. "layered.service-final"). */
    public function getId(): string;

    public function getName(): string;

    /**
     * @return list<string> List of violation messages (empty = passed).
     */
    public function validate(string $projectDir): array;
}
