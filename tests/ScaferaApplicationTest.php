<?php

declare(strict_types=1);

namespace Scafera\Kernel\Tests;

use PHPUnit\Framework\TestCase;
use Scafera\Kernel\Console\ScaferaApplication;
use Scafera\Kernel\ScaferaKernel;
use Symfony\Component\Console\Exception\CommandNotFoundException;

class ScaferaApplicationTest extends TestCase
{
    private ScaferaApplication $app;

    protected function setUp(): void
    {
        $_SERVER['APP_SECRET'] = 'test';
        $_ENV['APP_SECRET'] = 'test';

        $kernel = new ScaferaKernel('test', true, dirname(__DIR__, 4) . '/new/milestone3');
        $this->app = new ScaferaApplication($kernel);
    }

    public function testNameIsScafera(): void
    {
        $this->assertSame('Scafera', $this->app->getName());
    }

    public function testDefaultListExcludesSymfonyCommands(): void
    {
        $commands = $this->app->all();
        $names = array_keys($commands);

        $this->assertNotContains('debug:router', $names);
        $this->assertNotContains('debug:container', $names);
        $this->assertNotContains('config:dump-reference', $names);
    }

    public function testDefaultListIncludesBuiltinCommands(): void
    {
        $commands = $this->app->all();
        $names = array_keys($commands);

        $this->assertContains('help', $names);
        $this->assertContains('list', $names);
        $this->assertContains('about', $names);
    }

    public function testPromotedCommandAppearsInList(): void
    {
        $commands = $this->app->all();
        $names = array_keys($commands);

        $this->assertContains('cache:clear', $names);
    }

    public function testAliasedCommandAppearsWithAliasName(): void
    {
        $commands = $this->app->all();
        $names = array_keys($commands);

        $this->assertContains('route:list', $names);
    }

    public function testBlockedCommandThrowsWithHelpfulMessage(): void
    {
        $_SERVER['argv'] = ['scafera', 'debug:router'];

        $this->expectException(CommandNotFoundException::class);
        $this->expectExceptionMessage('scafera symfony debug:router');

        $this->app->doRun(
            new \Symfony\Component\Console\Input\ArrayInput(['command' => 'debug:router']),
            new \Symfony\Component\Console\Output\NullOutput()
        );
    }
}
