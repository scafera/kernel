<?php

declare(strict_types=1);

namespace Scafera\Kernel\Tests\Http;

use PHPUnit\Framework\TestCase;
use Scafera\Kernel\Http\Internal\RouteLoader;

class RouteLoaderTest extends TestCase
{
    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->fixtureDir = dirname(__DIR__) . '/Fixtures/Controller';

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->fixtureDir, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                require_once $file->getPathname();
            }
        }
    }

    public function testReturnsEmptyForNonExistentDirectory(): void
    {
        $routes = RouteLoader::loadFromDirectory('/tmp/non_existent_' . uniqid());

        $this->assertSame([], $routes);
    }

    public function testLoadsInvokableController(): void
    {
        $routes = RouteLoader::loadFromDirectory($this->fixtureDir);

        $name = 'scafera_kernel_tests_fixtures_controller_invokable_controller';
        $this->assertArrayHasKey($name, $routes);
        $this->assertSame('/home', $routes[$name]['path']);
        $this->assertSame(['GET'], $routes[$name]['methods']);
        $this->assertStringEndsWith('::__invoke', $routes[$name]['controller']);
    }

    public function testLoadsMethodLevelRoutes(): void
    {
        $routes = RouteLoader::loadFromDirectory($this->fixtureDir);

        $listName = 'scafera_kernel_tests_fixtures_controller_method_routes_controller_list';
        $showName = 'scafera_kernel_tests_fixtures_controller_method_routes_controller_show';

        $this->assertArrayHasKey($listName, $routes);
        $this->assertSame('/orders', $routes[$listName]['path']);
        $this->assertSame(['GET'], $routes[$listName]['methods']);

        $this->assertArrayHasKey($showName, $routes);
        $this->assertSame('/orders/{id}', $routes[$showName]['path']);
        $this->assertSame(['id' => '\d+'], $routes[$showName]['requirements']);
    }

    public function testClassLevelPrefixMergesWithMethodRoutes(): void
    {
        $routes = RouteLoader::loadFromDirectory($this->fixtureDir);

        $listName = 'scafera_kernel_tests_fixtures_controller_prefixed_controller_list';
        $showName = 'scafera_kernel_tests_fixtures_controller_prefixed_controller_show';

        $this->assertSame('/api/products', $routes[$listName]['path']);
        $this->assertSame('/api/products/{id}', $routes[$showName]['path']);
        $this->assertSame(['GET', 'HEAD'], $routes[$showName]['methods']);
    }

    public function testClassLevelDefaultsMergeIntoMethodRoutes(): void
    {
        $routes = RouteLoader::loadFromDirectory($this->fixtureDir);

        $listName = 'scafera_kernel_tests_fixtures_controller_prefixed_controller_list';
        $this->assertSame('json', $routes[$listName]['defaults']['_format']);
    }

    public function testExplicitNameOverridesGenerated(): void
    {
        $routes = RouteLoader::loadFromDirectory($this->fixtureDir);

        $this->assertArrayHasKey('dashboard', $routes);
        $this->assertSame('/dashboard', $routes['dashboard']['path']);
    }

    public function testSkipsClassWithNoRouteAttribute(): void
    {
        $routes = RouteLoader::loadFromDirectory($this->fixtureDir);

        foreach ($routes as $route) {
            $this->assertStringNotContainsString('NoRouteController', $route['controller']);
        }
    }

    public function testSkipsAbstractClasses(): void
    {
        $routes = RouteLoader::loadFromDirectory($this->fixtureDir);

        foreach ($routes as $route) {
            $this->assertStringNotContainsString('AbstractBaseController', $route['controller']);
        }
    }

    public function testReturnsEmptyForEmptyDirectory(): void
    {
        $tmpDir = sys_get_temp_dir() . '/scafera_empty_' . uniqid();
        mkdir($tmpDir, 0777, true);

        $routes = RouteLoader::loadFromDirectory($tmpDir);

        $this->assertSame([], $routes);

        rmdir($tmpDir);
    }
}
