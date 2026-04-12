<?php

declare(strict_types=1);

namespace Scafera\Kernel\Console\Internal;

use Scafera\Kernel\Console\Command;
use Scafera\Kernel\Console\Input;
use Scafera\Kernel\Console\Output;
use Scafera\Kernel\Contract\GeneratorInterface;
use Scafera\Kernel\Generator\FileWriter;

class MakeCommand extends Command
{
    private readonly GeneratorInterface $generator;

    /**
     * @param class-string<GeneratorInterface> $generatorClass
     */
    public function __construct(
        private readonly string $projectDir,
        string $generatorClass,
    ) {
        $this->generator = new $generatorClass();

        parent::__construct('make:' . $this->generator->getName());

        $this->setDescription($this->generator->getDescription());
        $this->addArg('name', 'Name of the file to generate (e.g. ImportUsers, Order/Create)');
    }

    protected function handle(Input $input, Output $output): int
    {
        $name = $input->argument('name');

        $inputs = ['name' => $name];

        foreach ($this->generator->getInputs() as $generatorInput) {
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
        $result = $this->generator->generate($this->projectDir, $inputs, $writer);

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
}
