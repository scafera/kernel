<?php

declare(strict_types=1);

namespace Scafera\Kernel\Tests;

use PHPUnit\Framework\TestCase;
use Scafera\Kernel\Bootstrap;

class BootstrapTest extends TestCase
{
    private string $tmpDir;
    private array $originalServer;
    private array $originalEnv;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/scafera_test_' . uniqid();
        mkdir($this->tmpDir . '/config', 0777, true);
        $this->originalServer = $_SERVER;
        $this->originalEnv = $_ENV;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        $_ENV = $this->originalEnv;
        $this->removeDir($this->tmpDir);
    }

    public function testThrowsWhenAppSecretMissing(): void
    {
        unset($_SERVER['APP_SECRET'], $_ENV['APP_SECRET']);
        putenv('APP_SECRET');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('APP_SECRET is not set');

        Bootstrap::init($this->tmpDir);
    }

    public function testSetsDefaultEnvValues(): void
    {
        unset($_SERVER['APP_ENV'], $_SERVER['APP_DEBUG'], $_ENV['APP_ENV'], $_ENV['APP_DEBUG']);

        // Provide APP_SECRET so init doesn't throw
        file_put_contents($this->tmpDir . '/config/config.yaml', "env:\n  APP_SECRET: test-secret\n");

        Bootstrap::init($this->tmpDir);

        $this->assertSame('dev', $_SERVER['APP_ENV']);
        $this->assertSame('1', $_SERVER['APP_DEBUG']);
    }

    public function testParsesEnvSectionWithTwoSpaceIndent(): void
    {
        unset($_SERVER['APP_SECRET'], $_ENV['APP_SECRET']);
        putenv('APP_SECRET');

        file_put_contents($this->tmpDir . '/config/config.yaml', "env:\n  APP_SECRET: my-secret\n");

        Bootstrap::init($this->tmpDir);

        $this->assertSame('my-secret', $_SERVER['APP_SECRET']);
    }

    public function testParsesEnvSectionWithFourSpaceIndent(): void
    {
        unset($_SERVER['APP_SECRET'], $_ENV['APP_SECRET']);
        putenv('APP_SECRET');

        file_put_contents($this->tmpDir . '/config/config.yaml', "env:\n    APP_SECRET: my-secret\n");

        Bootstrap::init($this->tmpDir);

        $this->assertSame('my-secret', $_SERVER['APP_SECRET']);
    }

    public function testOsEnvVarOverridesYaml(): void
    {
        putenv('APP_SECRET=from-os');
        $_SERVER['APP_SECRET'] = 'from-os';

        file_put_contents($this->tmpDir . '/config/config.yaml', "env:\n  APP_SECRET: from-yaml\n");

        Bootstrap::init($this->tmpDir);

        $this->assertSame('from-os', $_SERVER['APP_SECRET']);

        putenv('APP_SECRET');
    }

    public function testStopsParsingAtNextSection(): void
    {
        unset($_SERVER['APP_SECRET'], $_ENV['APP_SECRET'], $_SERVER['SHOULD_NOT_EXIST'], $_ENV['SHOULD_NOT_EXIST']);
        putenv('APP_SECRET');

        file_put_contents($this->tmpDir . '/config/config.yaml', "env:\n  APP_SECRET: secret\nframework:\n  SHOULD_NOT_EXIST: bad\n");

        Bootstrap::init($this->tmpDir);

        $this->assertSame('secret', $_SERVER['APP_SECRET']);
        $this->assertArrayNotHasKey('SHOULD_NOT_EXIST', $_SERVER);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
