<?php

declare(strict_types=1);

namespace Scafera\Kernel\Test;

use PHPUnit\Framework\Assert;
use Symfony\Component\Console\Tester\CommandTester;

final class CommandResult
{
    /** @internal */
    public function __construct(private readonly CommandTester $tester)
    {
    }

    public function assertSuccessful(): self
    {
        Assert::assertSame(0, $this->tester->getStatusCode(), 'Expected command to succeed (exit 0), got ' . $this->tester->getStatusCode());

        return $this;
    }

    public function assertFailed(): self
    {
        Assert::assertNotSame(0, $this->tester->getStatusCode(), 'Expected command to fail (non-zero exit), got 0');

        return $this;
    }

    public function assertExitCode(int $expected): self
    {
        Assert::assertSame($expected, $this->tester->getStatusCode());

        return $this;
    }

    public function assertOutputContains(string $needle): self
    {
        Assert::assertStringContainsString($needle, $this->getOutput());

        return $this;
    }

    public function assertOutputNotContains(string $needle): self
    {
        Assert::assertStringNotContainsString($needle, $this->getOutput());

        return $this;
    }

    public function getOutput(): string
    {
        return $this->tester->getDisplay();
    }

    public function getExitCode(): int
    {
        return $this->tester->getStatusCode();
    }
}
