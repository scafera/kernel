<?php

declare(strict_types=1);

namespace Scafera\Kernel\Console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class Output
{
    private readonly SymfonyStyle $io;

    /** @internal */
    public function __construct(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    public function write(string $message): void
    {
        $this->io->write($message);
    }

    public function writeln(string $message): void
    {
        $this->io->writeln($message);
    }

    public function newLine(int $count = 1): void
    {
        $this->io->newLine($count);
    }

    public function success(string|array $message): void
    {
        $this->io->success($message);
    }

    public function error(string|array $message): void
    {
        $this->io->error($message);
    }

    public function warning(string|array $message): void
    {
        $this->io->warning($message);
    }

    public function info(string|array $message): void
    {
        $this->io->info($message);
    }

    public function note(string|array $message): void
    {
        $this->io->note($message);
    }

    /** @param list<string> $headers */
    public function table(array $headers, array $rows): void
    {
        $this->io->table($headers, $rows);
    }

    public function confirm(string $question, bool $default = true): bool
    {
        return $this->io->confirm($question, $default);
    }

    public function isVerbose(): bool
    {
        return $this->io->isVerbose();
    }

    public function isQuiet(): bool
    {
        return $this->io->isQuiet();
    }
}
