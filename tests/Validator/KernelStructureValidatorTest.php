<?php

declare(strict_types=1);

namespace Scafera\Kernel\Tests\Validator;

use PHPUnit\Framework\TestCase;
use Scafera\Kernel\Validator\KernelStructureValidator;

class KernelStructureValidatorTest extends TestCase
{
    private KernelStructureValidator $validator;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->validator = new KernelStructureValidator();
        $this->tmpDir = sys_get_temp_dir() . '/scafera_test_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testPassesWhenAllRequiredPathsExist(): void
    {
        mkdir($this->tmpDir . '/src', 0777, true);
        mkdir($this->tmpDir . '/public', 0777, true);
        mkdir($this->tmpDir . '/var', 0777, true);
        file_put_contents($this->tmpDir . '/composer.json', '{}');

        $this->assertSame([], $this->validator->validate($this->tmpDir));
    }

    public function testFailsWhenComposerJsonMissing(): void
    {
        mkdir($this->tmpDir . '/src', 0777, true);
        mkdir($this->tmpDir . '/public', 0777, true);
        mkdir($this->tmpDir . '/var', 0777, true);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('composer.json', $violations[0]);
    }

    public function testFailsWhenSrcMissing(): void
    {
        mkdir($this->tmpDir . '/public', 0777, true);
        mkdir($this->tmpDir . '/var', 0777, true);
        file_put_contents($this->tmpDir . '/composer.json', '{}');

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('src/', $violations[0]);
    }

    public function testFailsWithMultipleMissing(): void
    {
        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(4, $violations);
    }

    public function testFailsWhenConfigPackagesExists(): void
    {
        $this->createValidStructure();
        mkdir($this->tmpDir . '/config/packages', 0777, true);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('config/packages', $violations[0]);
    }

    public function testFailsWhenSrcKernelExists(): void
    {
        $this->createValidStructure();
        file_put_contents($this->tmpDir . '/src/Kernel.php', '<?php class Kernel {}');

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('Kernel.php', $violations[0]);
    }

    public function testFailsWhenPublicIndexPhpExists(): void
    {
        $this->createValidStructure();
        file_put_contents($this->tmpDir . '/public/index.php', '<?php echo "hello";');

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('front controller', $violations[0]);
    }

    public function testName(): void
    {
        $this->assertSame('Kernel structure', $this->validator->getName());
    }

    private function createValidStructure(): void
    {
        mkdir($this->tmpDir . '/src', 0777, true);
        mkdir($this->tmpDir . '/public', 0777, true);
        mkdir($this->tmpDir . '/var', 0777, true);
        file_put_contents($this->tmpDir . '/composer.json', '{}');
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
    }
}
