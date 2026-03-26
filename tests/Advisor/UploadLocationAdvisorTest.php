<?php

declare(strict_types=1);

namespace Scafera\Kernel\Tests\Advisor;

use PHPUnit\Framework\TestCase;
use Scafera\Kernel\Advisor\UploadLocationAdvisor;

class UploadLocationAdvisorTest extends TestCase
{
    private UploadLocationAdvisor $advisor;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->advisor = new UploadLocationAdvisor();
        $this->tmpDir = sys_get_temp_dir() . '/scafera_test_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testSkippedWhenNoUploadDirectoriesDetected(): void
    {
        $this->assertSame('no upload directories detected', $this->advisor->skipped($this->tmpDir));
    }

    public function testNotSkippedWhenStorageExists(): void
    {
        mkdir($this->tmpDir . '/storage', 0777, true);

        $this->assertNull($this->advisor->skipped($this->tmpDir));
    }

    public function testAdvisesWhenStorageExists(): void
    {
        mkdir($this->tmpDir . '/storage', 0777, true);

        $hints = $this->advisor->advise($this->tmpDir);
        $this->assertCount(1, $hints);
        $this->assertStringContainsString('storage/', $hints[0]);
        $this->assertStringContainsString('var/upload/', $hints[0]);
    }

    public function testAdvisesWhenPublicUploadsExists(): void
    {
        mkdir($this->tmpDir . '/public/uploads', 0777, true);

        $hints = $this->advisor->advise($this->tmpDir);
        $this->assertCount(1, $hints);
        $this->assertStringContainsString('public/uploads/', $hints[0]);
    }

    public function testAdvisesWhenVarUploadsPluralExists(): void
    {
        mkdir($this->tmpDir . '/var/uploads', 0777, true);

        $hints = $this->advisor->advise($this->tmpDir);
        $this->assertCount(1, $hints);
        $this->assertStringContainsString('singular', $hints[0]);
    }

    public function testMultipleSuspiciousPaths(): void
    {
        mkdir($this->tmpDir . '/storage', 0777, true);
        mkdir($this->tmpDir . '/public/uploads', 0777, true);

        $hints = $this->advisor->advise($this->tmpDir);
        $this->assertCount(2, $hints);
    }

    public function testName(): void
    {
        $this->assertSame('Upload location', $this->advisor->getName());
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
