<?php

declare(strict_types=1);

namespace Scafera\Kernel\Console;

use Symfony\Component\Console\Input\InputInterface;

final class Input
{
    /** @internal */
    public function __construct(private readonly InputInterface $inner)
    {
    }

    public function argument(string $name): mixed
    {
        return $this->inner->getArgument($name);
    }

    public function option(string $name): mixed
    {
        return $this->inner->getOption($name);
    }

    public function hasArgument(string $name): bool
    {
        return $this->inner->hasArgument($name);
    }

    public function hasOption(string $name): bool
    {
        return $this->inner->hasOption($name);
    }

    /** @return array<string, mixed> */
    public function arguments(): array
    {
        return $this->inner->getArguments();
    }

    /** @return array<string, mixed> */
    public function options(): array
    {
        return $this->inner->getOptions();
    }
}
