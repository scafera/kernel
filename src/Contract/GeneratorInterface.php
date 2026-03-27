<?php

declare(strict_types=1);

namespace Scafera\Kernel\Contract;

use Scafera\Kernel\Generator\FileWriter;
use Scafera\Kernel\Generator\GeneratorInput;
use Scafera\Kernel\Generator\GeneratorResult;

interface GeneratorInterface
{
    public function getName(): string;

    public function getDescription(): string;

    /** @return list<GeneratorInput> */
    public function getInputs(): array;

    /**
     * @param array<string, string> $inputs
     */
    public function generate(string $projectDir, array $inputs, FileWriter $writer): GeneratorResult;
}
