<?php

declare(strict_types=1);

namespace Scafera\Kernel\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Scafera\Kernel\Contract\ArchitecturePackageInterface;
use Scafera\Kernel\DependencyInjection\ControllerBoundaryPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ControllerBoundaryPassTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/scafera_boundary_test_' . uniqid();
        mkdir($this->tmpDir . '/src/Controller', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testSkipsWhenNoArchitecture(): void
    {
        $pass = new ControllerBoundaryPass($this->tmpDir, null);
        $pass->process(new ContainerBuilder());

        $this->addToAssertionCount(1);
    }

    public function testPassesWithCleanController(): void
    {
        file_put_contents($this->tmpDir . '/src/Controller/HomeController.php', <<<'PHP'
        <?php
        namespace App\Controller;
        use Scafera\Kernel\Http\Route;
        class HomeController {}
        PHP);

        $pass = new ControllerBoundaryPass($this->tmpDir, $this->createArchitecture(['src/Controller']));
        $pass->process(new ContainerBuilder());

        $this->addToAssertionCount(1);
    }

    public function testDetectsSymfonyImport(): void
    {
        file_put_contents($this->tmpDir . '/src/Controller/BadController.php', <<<'PHP'
        <?php
        namespace App\Controller;
        use Symfony\Component\HttpFoundation\Response;
        class BadController {}
        PHP);

        $pass = new ControllerBoundaryPass($this->tmpDir, $this->createArchitecture(['src/Controller']));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('imports a Symfony class');

        $pass->process(new ContainerBuilder());
    }

    public function testDetectsAbstractControllerExtension(): void
    {
        file_put_contents($this->tmpDir . '/src/Controller/BadController.php', <<<'PHP'
        <?php
        namespace App\Controller;
        class BadController extends AbstractController {}
        PHP);

        $pass = new ControllerBoundaryPass($this->tmpDir, $this->createArchitecture(['src/Controller']));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('extends Symfony\'s AbstractController');

        $pass->process(new ContainerBuilder());
    }

    public function testReportsMultipleViolationsInSingleFile(): void
    {
        file_put_contents($this->tmpDir . '/src/Controller/BadController.php', <<<'PHP'
        <?php
        namespace App\Controller;
        use Symfony\Component\HttpFoundation\Response;
        class BadController extends AbstractController {}
        PHP);

        $pass = new ControllerBoundaryPass($this->tmpDir, $this->createArchitecture(['src/Controller']));

        try {
            $pass->process(new ContainerBuilder());
            $this->fail('Expected LogicException');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('imports a Symfony class', $e->getMessage());
            $this->assertStringContainsString('extends Symfony\'s AbstractController', $e->getMessage());
        }
    }

    public function testSkipsNonPhpFiles(): void
    {
        file_put_contents($this->tmpDir . '/src/Controller/notes.txt', 'use Symfony\Component\HttpFoundation\Response;');

        $pass = new ControllerBoundaryPass($this->tmpDir, $this->createArchitecture(['src/Controller']));
        $pass->process(new ContainerBuilder());

        $this->addToAssertionCount(1);
    }

    public function testSkipsMissingControllerDirectory(): void
    {
        $pass = new ControllerBoundaryPass($this->tmpDir, $this->createArchitecture(['src/NonExistent']));
        $pass->process(new ContainerBuilder());

        $this->addToAssertionCount(1);
    }

    public function testScansSubdirectories(): void
    {
        mkdir($this->tmpDir . '/src/Controller/Admin', 0777, true);
        file_put_contents($this->tmpDir . '/src/Controller/Admin/DashboardController.php', <<<'PHP'
        <?php
        namespace App\Controller\Admin;
        use Symfony\Component\HttpFoundation\JsonResponse;
        class DashboardController {}
        PHP);

        $pass = new ControllerBoundaryPass($this->tmpDir, $this->createArchitecture(['src/Controller']));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('imports a Symfony class');

        $pass->process(new ContainerBuilder());
    }

    private function createArchitecture(array $controllerPaths): ArchitecturePackageInterface
    {
        $arch = $this->createStub(ArchitecturePackageInterface::class);
        $arch->method('getControllerPaths')->willReturn($controllerPaths);

        return $arch;
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
