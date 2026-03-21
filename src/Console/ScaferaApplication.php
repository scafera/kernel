<?php

declare(strict_types=1);

namespace Scafera\Kernel\Console;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Composer\InstalledVersions;
use Scafera\Kernel\Console\Command\AboutCommand;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class ScaferaApplication extends Application
{
    private bool $symfonyPassthrough = false;

    private const PROMOTED_COMMANDS = [
        'cache:clear' => null,
        'route:list'  => 'debug:router',
    ];

    public function __construct(KernelInterface $kernel)
    {
        parent::__construct($kernel);
        $this->setName('Scafera');
        $this->setVersion(InstalledVersions::getVersion('scafera/kernel') ?? 'dev');
    }

    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        $argv = $_SERVER['argv'] ?? [];

        if (isset($argv[1]) && $argv[1] === 'symfony') {
            $this->symfonyPassthrough = true;
            array_splice($argv, 1, 1);
            $input = new ArgvInput($argv);
        }

        if (!$this->symfonyPassthrough) {
            $name = $input->getFirstArgument();
            if ($name && str_contains($name, ':') && !str_starts_with($name, 'app:') && !$this->isPromoted($name)) {
                throw new CommandNotFoundException(
                    sprintf('Command "%s" is a Symfony command. Run "scafera symfony %s" to use it.', $name, $name)
                );
            }

            if ($name && $this->isPromoted($name) && self::PROMOTED_COMMANDS[$name] !== null) {
                foreach ($argv as $i => $arg) {
                    if ($arg === $name) {
                        $argv[$i] = self::PROMOTED_COMMANDS[$name];
                        break;
                    }
                }
                $input = new ArgvInput($argv);
            }
        }

        return parent::doRun($input, $output);
    }

    public function all(?string $namespace = null): array
    {
        $commands = parent::all($namespace);

        // Replace Symfony's about with Scafera's
        if (isset($commands['about'])) {
            $commands['about'] = new AboutCommand();
        }

        if ($this->symfonyPassthrough) {
            return $commands;
        }

        $filtered = array_filter($commands, function (Command $command) {
            $name = $command->getName();
            return !str_contains($name, ':') || str_starts_with($name, 'app:') || $this->isPromoted($name);
        });

        // Add aliased commands to the list under their Scafera names
        $allCommands = $namespace !== null ? parent::all() : $commands;
        foreach (self::PROMOTED_COMMANDS as $alias => $target) {
            if ($target !== null && isset($allCommands[$target]) && !isset($filtered[$alias])) {
                if ($namespace === null || str_starts_with($alias, $namespace . ':')) {
                    $cmd = clone $allCommands[$target];
                    $cmd->setName($alias);
                    $filtered[$alias] = $cmd;
                }
            }
        }

        return $filtered;
    }

    public function find(string $name): Command
    {
        if ($name === 'about') {
            $cmd = new AboutCommand();
            $cmd->setApplication($this);
            return $cmd;
        }

        return parent::find($name);
    }

    private function isPromoted(string $name): bool
    {
        return array_key_exists($name, self::PROMOTED_COMMANDS);
    }
}
