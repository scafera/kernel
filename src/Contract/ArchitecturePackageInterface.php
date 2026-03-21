<?php

declare(strict_types=1);

namespace Scafera\Kernel\Contract;

interface ArchitecturePackageInterface
{
    public function getName(): string;

    /**
     * @return array{namespace: string, resource: string, exclude: list<string>}
     */
    public function getServiceDiscovery(string $projectDir): array;

    /** @return list<string> Relative paths to controller directories (for attribute routing) */
    public function getControllerPaths(): array;

    /** @return array<string, string> Folder path => description */
    public function getStructure(): array;

    /** @return list<string> Validator class names */
    public function getValidators(): array;

    /** @return list<string> Generator class names */
    public function getGenerators(): array;
}
