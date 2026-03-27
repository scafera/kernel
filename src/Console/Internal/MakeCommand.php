<?php

declare(strict_types=1);

namespace Scafera\Kernel\Console\Internal;

use Scafera\Kernel\Console\Attribute\AsCommand;
use Scafera\Kernel\Console\Command;
use Scafera\Kernel\Console\Input;
use Scafera\Kernel\Console\Output;
use Scafera\Kernel\Contract\ArchitecturePackageInterface;
use Scafera\Kernel\Contract\GeneratorInterface;
use Scafera\Kernel\Generator\FileWriter;
use Scafera\Kernel\InstalledPackages;

#[AsCommand('make', description: 'Generate project files')]
class MakeCommand extends Command
{
    public function __construct(
        private readonly string $projectDir,
    ) {
        parent::__construct();

        $this->addArg('type', 'Generator type (e.g. controller, service)', required: false);
        $this->addArg('name', 'Name of the file to generate', required: false);
    }

    protected function handle(Input $input, Output $output): int
    {
        $architecture = $this->resolveArchitecture();

        if ($architecture === null) {
            $output->error('No architecture package installed. Generators require an architecture package.');

            return self::FAILURE;
        }

        $generators = $this->resolveGenerators($architecture);

        $type = $input->argument('type');

        if (!$type) {
            return $this->listGenerators($generators, $architecture, $output);
        }

        $generator = $generators[$type] ?? null;

        if ($generator === null) {
            $available = implode(', ', array_keys($generators));
            $output->error("Unknown generator '{$type}'. Available: {$available}");

            return self::FAILURE;
        }

        $name = $input->argument('name');

        if (!$name) {
            $output->error("The 'name' argument is required. Usage: scafera make {$type} <name>");

            return self::FAILURE;
        }

        $inputs = ['name' => $name];

        // Validate required inputs beyond 'name'
        foreach ($generator->getInputs() as $generatorInput) {
            if ($generatorInput->name === 'name') {
                continue;
            }

            if ($generatorInput->required && !isset($inputs[$generatorInput->name])) {
                if ($generatorInput->default !== null) {
                    $inputs[$generatorInput->name] = $generatorInput->default;
                } else {
                    $output->error("Missing required input: {$generatorInput->name} ({$generatorInput->description})");

                    return self::FAILURE;
                }
            }
        }

        $writer = new FileWriter();
        $result = $generator->generate($this->projectDir, $inputs, $writer);

        if (empty($result->filesCreated)) {
            foreach ($result->messages as $message) {
                $output->error($message);
            }

            if (empty($result->messages)) {
                $output->warning('No files were created.');
            }

            return self::FAILURE;
        }

        foreach ($result->filesCreated as $file) {
            $output->writeln('  <fg=green>created</> ' . $file);
        }

        foreach ($result->messages as $message) {
            $output->writeln('  <fg=blue>ℹ</> ' . $message);
        }

        $output->writeln('');
        $output->success(count($result->filesCreated) . ' file(s) created.');

        return self::SUCCESS;
    }

    /**
     * @param array<string, GeneratorInterface> $generators
     */
    private function listGenerators(array $generators, ArchitecturePackageInterface $architecture, Output $output): int
    {
        $output->writeln('<comment>Available generators (' . $architecture->getName() . ')</comment>');
        $output->writeln('');

        if (empty($generators)) {
            $output->info('No generators defined by this architecture.');

            return self::SUCCESS;
        }

        foreach ($generators as $generator) {
            $output->writeln('  <fg=green>' . $generator->getName() . '</> — ' . $generator->getDescription());
        }

        $output->writeln('');
        $output->writeln('Usage: scafera make <type> <name>');

        return self::SUCCESS;
    }

    /**
     * @return array<string, GeneratorInterface>
     */
    private function resolveGenerators(ArchitecturePackageInterface $architecture): array
    {
        $generators = [];

        foreach ($architecture->getGenerators() as $class) {
            if (!class_exists($class) || !is_subclass_of($class, GeneratorInterface::class)) {
                continue;
            }

            $generator = new $class();
            $generators[$generator->getName()] = $generator;
        }

        return $generators;
    }

    private function resolveArchitecture(): ?ArchitecturePackageInterface
    {
        $installed = InstalledPackages::get($this->projectDir);
        $class = $installed['architecture'];

        if ($class && class_exists($class) && is_subclass_of($class, ArchitecturePackageInterface::class)) {
            return new $class();
        }

        return null;
    }
}
