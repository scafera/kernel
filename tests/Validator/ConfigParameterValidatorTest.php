<?php

declare(strict_types=1);

namespace Scafera\Kernel\Tests\Validator;

use PHPUnit\Framework\TestCase;
use Scafera\Kernel\Validator\ConfigParameterValidator;

class ConfigParameterValidatorTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/scafera_config_validator_' . uniqid();
        mkdir($this->tmpDir . '/src/Service', 0777, true);
        mkdir($this->tmpDir . '/config', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testName(): void
    {
        $validator = new ConfigParameterValidator();

        $this->assertSame('Config parameter references', $validator->getName());
    }

    public function testPassesWhenNoConfigUsages(): void
    {
        file_put_contents($this->tmpDir . '/src/Service/OrderService.php', <<<'PHP'
        <?php
        namespace App\Service;
        final class OrderService {}
        PHP);

        $validator = new ConfigParameterValidator();
        $violations = $validator->validate($this->tmpDir);

        $this->assertSame([], $violations);
    }

    public function testPassesWhenParameterIsDefined(): void
    {
        file_put_contents($this->tmpDir . '/config/config.yaml', <<<'YAML'
        parameters:
            app.items_per_page: 25
        YAML);

        file_put_contents($this->tmpDir . '/src/Service/OrderService.php', <<<'PHP'
        <?php
        namespace App\Service;
        use Scafera\Kernel\Attribute\Config;
        final class OrderService {
            public function __construct(
                #[Config('app.items_per_page')] private readonly int $perPage,
            ) {}
        }
        PHP);

        $validator = new ConfigParameterValidator();
        $violations = $validator->validate($this->tmpDir);

        $this->assertSame([], $violations);
    }

    public function testFailsWhenParameterIsUndefined(): void
    {
        file_put_contents($this->tmpDir . '/config/config.yaml', <<<'YAML'
        parameters:
            app.site_name: 'My App'
        YAML);

        file_put_contents($this->tmpDir . '/src/Service/OrderService.php', <<<'PHP'
        <?php
        namespace App\Service;
        use Scafera\Kernel\Attribute\Config;
        final class OrderService {
            public function __construct(
                #[Config('app.items_per_page')] private readonly int $perPage,
            ) {}
        }
        PHP);

        $validator = new ConfigParameterValidator();
        $violations = $validator->validate($this->tmpDir);

        $this->assertCount(1, $violations);
        $this->assertStringContainsString('app.items_per_page', $violations[0]);
        $this->assertStringContainsString('undefined parameter', $violations[0]);
    }

    public function testFailsWhenNoConfigFileExists(): void
    {
        file_put_contents($this->tmpDir . '/src/Service/OrderService.php', <<<'PHP'
        <?php
        namespace App\Service;
        use Scafera\Kernel\Attribute\Config;
        final class OrderService {
            public function __construct(
                #[Config('app.items_per_page')] private readonly int $perPage,
            ) {}
        }
        PHP);

        $validator = new ConfigParameterValidator();
        $violations = $validator->validate($this->tmpDir);

        $this->assertCount(1, $violations);
        $this->assertStringContainsString('app.items_per_page', $violations[0]);
    }

    public function testSkipsEnvPrefixedKeys(): void
    {
        file_put_contents($this->tmpDir . '/src/Service/OrderService.php', <<<'PHP'
        <?php
        namespace App\Service;
        use Scafera\Kernel\Attribute\Config;
        final class OrderService {
            public function __construct(
                #[Config('env.DATABASE_URL')] private readonly string $dbUrl,
            ) {}
        }
        PHP);

        $validator = new ConfigParameterValidator();
        $violations = $validator->validate($this->tmpDir);

        $this->assertSame([], $violations);
    }

    public function testDetectsMultipleUndefinedParameters(): void
    {
        file_put_contents($this->tmpDir . '/config/config.yaml', <<<'YAML'
        parameters:
            app.site_name: 'My App'
        YAML);

        file_put_contents($this->tmpDir . '/src/Service/OrderService.php', <<<'PHP'
        <?php
        namespace App\Service;
        use Scafera\Kernel\Attribute\Config;
        final class OrderService {
            public function __construct(
                #[Config('app.items_per_page')] private readonly int $perPage,
                #[Config('app.max_upload')] private readonly int $maxUpload,
            ) {}
        }
        PHP);

        $validator = new ConfigParameterValidator();
        $violations = $validator->validate($this->tmpDir);

        $this->assertCount(2, $violations);
    }

    public function testPassesWithNoSrcDirectory(): void
    {
        $emptyDir = sys_get_temp_dir() . '/scafera_empty_' . uniqid();
        mkdir($emptyDir);

        $validator = new ConfigParameterValidator();
        $violations = $validator->validate($emptyDir);

        $this->assertSame([], $violations);

        rmdir($emptyDir);
    }

    public function testScansSubdirectories(): void
    {
        mkdir($this->tmpDir . '/src/Service/Order', 0777, true);

        file_put_contents($this->tmpDir . '/config/config.yaml', <<<'YAML'
        parameters:
            app.site_name: 'My App'
        YAML);

        file_put_contents($this->tmpDir . '/src/Service/Order/PlaceOrder.php', <<<'PHP'
        <?php
        namespace App\Service\Order;
        use Scafera\Kernel\Attribute\Config;
        final class PlaceOrder {
            public function __construct(
                #[Config('app.tax_rate')] private readonly float $taxRate,
            ) {}
        }
        PHP);

        $validator = new ConfigParameterValidator();
        $violations = $validator->validate($this->tmpDir);

        $this->assertCount(1, $violations);
        $this->assertStringContainsString('app.tax_rate', $violations[0]);
    }

    public function testStopsParsingAtNextTopLevelKey(): void
    {
        file_put_contents($this->tmpDir . '/config/config.yaml', <<<'YAML'
        parameters:
            app.items_per_page: 25

        framework:
            secret: '%env(APP_SECRET)%'
        YAML);

        file_put_contents($this->tmpDir . '/src/Service/OrderService.php', <<<'PHP'
        <?php
        namespace App\Service;
        use Scafera\Kernel\Attribute\Config;
        final class OrderService {
            public function __construct(
                #[Config('app.items_per_page')] private readonly int $perPage,
            ) {}
        }
        PHP);

        $validator = new ConfigParameterValidator();
        $violations = $validator->validate($this->tmpDir);

        $this->assertSame([], $violations);
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
