<?php

declare(strict_types=1);

namespace Scafera\Kernel\Tests;

use PHPUnit\Framework\TestCase;
use Scafera\Kernel\InstalledPackages;

class InstalledPackagesTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/scafera_test_' . uniqid();
        mkdir($this->tmpDir . '/var/cache', 0777, true);
        mkdir($this->tmpDir . '/vendor/composer', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testReturnsEmptyWhenNoInstalledJson(): void
    {
        $result = InstalledPackages::get($this->tmpDir);

        $this->assertSame([], $result['bundles']);
        $this->assertNull($result['architecture']);
    }

    public function testReturnsEmptyOnMalformedJson(): void
    {
        file_put_contents($this->tmpDir . '/vendor/composer/installed.json', 'not json');

        // Clear any existing cache
        @unlink($this->tmpDir . '/var/cache/installed_packages.php');

        $result = InstalledPackages::get($this->tmpDir);

        $this->assertSame([], $result['bundles']);
        $this->assertNull($result['architecture']);
    }

    public function testFindsArchitectureFromExtra(): void
    {
        $this->writeInstalledJson([
            [
                'name' => 'scafera/layered',
                'type' => 'library',
                'extra' => ['scafera-architecture' => 'Scafera\\Layered\\LayeredArchitecture'],
            ],
        ]);

        $result = InstalledPackages::get($this->tmpDir);

        $this->assertSame('Scafera\\Layered\\LayeredArchitecture', $result['architecture']);
    }

    public function testSkipsFrameworkBundle(): void
    {
        $this->writeInstalledJson([
            [
                'name' => 'symfony/framework-bundle',
                'type' => 'symfony-bundle',
                'install-path' => '../symfony/framework-bundle',
                'autoload' => ['psr-4' => ['Symfony\\Bundle\\FrameworkBundle\\' => '']],
            ],
        ]);

        $result = InstalledPackages::get($this->tmpDir);

        $this->assertSame([], $result['bundles']);
    }

    public function testWritesAndReadsCacheFile(): void
    {
        $this->writeInstalledJson([]);

        // First call writes cache
        InstalledPackages::get($this->tmpDir);

        $this->assertFileExists($this->tmpDir . '/var/cache/installed_packages.php');

        // Second call reads from cache
        $result = InstalledPackages::get($this->tmpDir);

        $this->assertSame([], $result['bundles']);
        $this->assertNull($result['architecture']);
    }

    public function testDiscoversBundleClassViaGlob(): void
    {
        // Create a fake bundle package directory
        $bundleDir = $this->tmpDir . '/vendor/test/my-bundle';
        mkdir($bundleDir, 0777, true);
        file_put_contents($bundleDir . '/MyTestBundle.php', '<?php');

        $this->writeInstalledJson([
            [
                'name' => 'test/my-bundle',
                'type' => 'symfony-bundle',
                'install-path' => '../test/my-bundle',
                'autoload' => ['psr-4' => ['Test\\MyBundle\\' => '']],
            ],
        ]);

        $result = InstalledPackages::get($this->tmpDir);

        $this->assertSame(['Test\\MyBundle\\MyTestBundle'], $result['bundles']);
    }

    public function testDiscoverCompanionBundleWhenPackageInstalled(): void
    {
        $this->writeInstalledJson([
            [
                'name' => 'scafera/asset',
                'type' => 'symfony-bundle',
                'extra' => [
                    'scafera-bundles' => [
                        'vendor/some-bundle' => 'Vendor\\SomeBundle\\SomeBundle',
                    ],
                ],
            ],
            [
                'name' => 'vendor/some-bundle',
                'type' => 'library',
            ],
        ]);

        $result = InstalledPackages::get($this->tmpDir);

        $this->assertContains('Vendor\\SomeBundle\\SomeBundle', $result['bundles']);
    }

    public function testIgnoresCompanionWhenPackageNotInstalled(): void
    {
        $this->writeInstalledJson([
            [
                'name' => 'scafera/asset',
                'type' => 'symfony-bundle',
                'extra' => [
                    'scafera-bundles' => [
                        'vendor/not-installed' => 'Vendor\\NotInstalled\\SomeBundle',
                    ],
                ],
            ],
        ]);

        $result = InstalledPackages::get($this->tmpDir);

        $this->assertNotContains('Vendor\\NotInstalled\\SomeBundle', $result['bundles']);
    }

    public function testDeduplicatesCompanionWithTypeDiscovery(): void
    {
        $bundleDir = $this->tmpDir . '/vendor/test/my-bundle';
        mkdir($bundleDir, 0777, true);
        file_put_contents($bundleDir . '/MyTestBundle.php', '<?php');

        $this->writeInstalledJson([
            [
                'name' => 'test/my-bundle',
                'type' => 'symfony-bundle',
                'install-path' => '../test/my-bundle',
                'autoload' => ['psr-4' => ['Test\\MyBundle\\' => '']],
            ],
            [
                'name' => 'scafera/asset',
                'type' => 'symfony-bundle',
                'extra' => [
                    'scafera-bundles' => [
                        'test/my-bundle' => 'Test\\MyBundle\\MyTestBundle',
                    ],
                ],
            ],
        ]);

        $result = InstalledPackages::get($this->tmpDir);

        $this->assertSame(1, \count(array_filter($result['bundles'], fn($b) => $b === 'Test\\MyBundle\\MyTestBundle')));
    }

    public function testDoesNotDiscoverLibraryTypeBundles(): void
    {
        $bundleDir = $this->tmpDir . '/vendor/vendor/lib-bundle';
        mkdir($bundleDir, 0777, true);
        file_put_contents($bundleDir . '/LibBundle.php', '<?php');

        $this->writeInstalledJson([
            [
                'name' => 'vendor/lib-bundle',
                'type' => 'library',
                'install-path' => '../vendor/lib-bundle',
                'autoload' => ['psr-4' => ['Vendor\\LibBundle\\' => '']],
            ],
        ]);

        $result = InstalledPackages::get($this->tmpDir);

        $this->assertNotContains('Vendor\\LibBundle\\LibBundle', $result['bundles']);
    }

    public function testThrowsOnMultipleArchitecturePackages(): void
    {
        $this->writeInstalledJson([
            [
                'name' => 'scafera/layered',
                'type' => 'library',
                'extra' => ['scafera-architecture' => 'Scafera\\Layered\\LayeredArchitecture'],
            ],
            [
                'name' => 'scafera/hexagonal',
                'type' => 'library',
                'extra' => ['scafera-architecture' => 'Scafera\\Hexagonal\\HexagonalArchitecture'],
            ],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Multiple Scafera architecture packages detected: scafera/layered, scafera/hexagonal');

        InstalledPackages::get($this->tmpDir);
    }

    private function writeInstalledJson(array $packages): void
    {
        // Clear cache so parse() runs
        @unlink($this->tmpDir . '/var/cache/installed_packages.php');

        file_put_contents(
            $this->tmpDir . '/vendor/composer/installed.json',
            json_encode(['packages' => $packages])
        );
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
