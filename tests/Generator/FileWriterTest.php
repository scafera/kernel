<?php

declare(strict_types=1);

namespace Scafera\Kernel\Tests\Generator;

use PHPUnit\Framework\TestCase;
use Scafera\Kernel\Generator\FileWriter;

class FileWriterTest extends TestCase
{
    private FileWriter $writer;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->writer = new FileWriter();
        $this->tmpDir = sys_get_temp_dir() . '/scafera_test_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testWriteCreatesFileAndDirectories(): void
    {
        $path = $this->writer->write($this->tmpDir, 'src/Controller/Home.php', '<?php // home');

        $this->assertSame('src/Controller/Home.php', $path);
        $this->assertFileExists($this->tmpDir . '/src/Controller/Home.php');
        $this->assertSame('<?php // home', file_get_contents($this->tmpDir . '/src/Controller/Home.php'));
    }

    public function testExistsReturnsTrueForExistingFile(): void
    {
        $this->writer->write($this->tmpDir, 'src/Test.php', '<?php');

        $this->assertTrue($this->writer->exists($this->tmpDir, 'src/Test.php'));
    }

    public function testExistsReturnsFalseForMissingFile(): void
    {
        $this->assertFalse($this->writer->exists($this->tmpDir, 'src/Missing.php'));
    }

    public function testPathToNamespace(): void
    {
        $this->assertSame('App\\Controller\\Api\\Status', $this->writer->pathToNamespace('src/Controller/Api/Status.php'));
        $this->assertSame('App\\Controller\\Home', $this->writer->pathToNamespace('src/Controller/Home.php'));
        $this->assertSame('App\\Service\\OrderProcessor', $this->writer->pathToNamespace('src/Service/OrderProcessor.php'));
    }

    public function testClassToPath(): void
    {
        $this->assertSame('src/Controller/Api/Status.php', $this->writer->classToPath('App\\Controller\\Api\\Status'));
        $this->assertSame('src/Controller/Home.php', $this->writer->classToPath('App\\Controller\\Home'));
    }

    public function testToPascalCase(): void
    {
        $this->assertSame('OrderList', $this->writer->toPascalCase('order-list'));
        $this->assertSame('OrderList', $this->writer->toPascalCase('order_list'));
        $this->assertSame('Home', $this->writer->toPascalCase('home'));
        $this->assertSame('Home', $this->writer->toPascalCase('Home'));
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
