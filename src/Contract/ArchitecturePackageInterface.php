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

    /** @return list<ValidatorInterface> Validator instances */
    public function getValidators(): array;

    /** @return list<GeneratorInterface> Generator instances */
    public function getGenerators(): array;

    /** @return list<AdvisorInterface> Advisor instances (warnings, never block) */
    public function getAdvisors(): array;
}
