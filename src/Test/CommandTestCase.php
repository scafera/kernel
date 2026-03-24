<?php

declare(strict_types=1);

namespace Scafera\Kernel\Test;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

abstract class CommandTestCase extends KernelTestCase
{
    protected function runCommand(string $name, array $input = []): CommandResult
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);

        $command = $application->find($name);
        $tester = new CommandTester($command);
        $tester->execute($input);

        return new CommandResult($tester);
    }
}
