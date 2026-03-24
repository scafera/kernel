<?php

declare(strict_types=1);

namespace Scafera\Kernel\Console;

use Scafera\Kernel\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class Command extends SymfonyCommand
{
    public function __construct()
    {
        $ref = new \ReflectionClass(static::class);
        $attrs = $ref->getAttributes(AsCommand::class);

        if (!empty($attrs)) {
            $attr = $attrs[0]->newInstance();
            parent::__construct($attr->name);
            $this->setDescription($attr->description);
        } else {
            parent::__construct();
        }
    }

    /**
     * Implement this method with your command logic.
     */
    abstract protected function handle(Input $input, Output $output): int;

    /** @internal Bridges Symfony's execute() to Scafera's handle(). */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->handle(new Input($input), new Output($input, $output));
    }

    protected function addArg(string $name, string $description = '', bool $required = true, mixed $default = null): static
    {
        $this->addArgument($name, $required ? InputArgument::REQUIRED : InputArgument::OPTIONAL, $description, $default);

        return $this;
    }

    protected function addFlag(string $name, ?string $shortcut = null, string $description = ''): static
    {
        $this->addOption($name, $shortcut, InputOption::VALUE_NONE, $description);

        return $this;
    }

    protected function addOpt(string $name, ?string $shortcut = null, string $description = '', mixed $default = null): static
    {
        $this->addOption($name, $shortcut, InputOption::VALUE_REQUIRED, $description, $default);

        return $this;
    }
}
