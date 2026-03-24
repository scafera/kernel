<?php

declare(strict_types=1);

namespace Scafera\Kernel\Http\Internal;

use Scafera\Kernel\Http\Route;

/**
 * @internal Reads Scafera\Http\Route attributes and returns route definitions.
 *           Called directly by ScaferaKernel — not a Symfony loader service.
 */
final class RouteLoader
{
    /**
     * Scans a directory for PHP classes with #[Route] attributes.
     *
     * @return array<string, array{path: string, controller: string, methods: list<string>, requirements: array<string, string>, defaults: array<string, mixed>}>
     */
    public static function loadFromDirectory(string $dir): array
    {
        $dir = realpath($dir);
        if (!$dir || !is_dir($dir)) {
            return [];
        }

        $routes = [];
        foreach (self::findClasses($dir) as $class) {
            $routes = array_merge($routes, self::loadFromClass($class));
        }

        return $routes;
    }

    /** @return array<string, array{path: string, controller: string, methods: list<string>, requirements: array<string, string>, defaults: array<string, mixed>}> */
    private static function loadFromClass(string $class): array
    {
        $ref = new \ReflectionClass($class);
        if ($ref->isAbstract() || $ref->isInterface() || $ref->isTrait()) {
            return [];
        }

        $classRoute = null;
        $classAttrs = $ref->getAttributes(Route::class);
        if (!empty($classAttrs)) {
            $classRoute = $classAttrs[0]->newInstance();
        }

        // Collect method-level routes.
        $methodRoutes = [];
        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor() || $method->getDeclaringClass()->getName() !== $class) {
                continue;
            }
            foreach ($method->getAttributes(Route::class) as $attr) {
                $methodRoutes[] = [$method->getName(), $attr->newInstance()];
            }
        }

        $routes = [];

        if (empty($methodRoutes) && $classRoute !== null) {
            // Invokable controller: class-level route maps to __invoke.
            $name = $classRoute->name ?: self::generateName($class, '__invoke');
            $routes[$name] = [
                'path' => $classRoute->path,
                'controller' => $class . '::__invoke',
                'methods' => $classRoute->normalizedMethods,
                'requirements' => $classRoute->requirements,
                'defaults' => $classRoute->defaults,
            ];
        } else {
            // Method-level routes, with optional class-level prefix.
            $prefix = $classRoute ? rtrim($classRoute->path, '/') : '';
            $prefixDefaults = $classRoute ? $classRoute->defaults : [];

            foreach ($methodRoutes as [$methodName, $route]) {
                $name = $route->name ?: self::generateName($class, $methodName);
                $routes[$name] = [
                    'path' => $prefix . $route->path,
                    'controller' => $class . '::' . $methodName,
                    'methods' => $route->normalizedMethods,
                    'requirements' => $route->requirements,
                    'defaults' => array_merge($prefixDefaults, $route->defaults),
                ];
            }
        }

        return $routes;
    }

    /** @return list<class-string> */
    private static function findClasses(string $dir): array
    {
        $classes = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $class = self::extractClassName($file->getPathname());
            if ($class !== null && class_exists($class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    private static function extractClassName(string $file): ?string
    {
        $contents = file_get_contents($file);
        $namespace = null;
        $class = null;

        if (preg_match('/namespace\s+([^;]+);/', $contents, $m)) {
            $namespace = $m[1];
        }
        if (preg_match('/\b(?:class|enum)\s+(\w+)/', $contents, $m)) {
            $class = $m[1];
        }

        if ($namespace !== null && $class !== null) {
            return $namespace . '\\' . $class;
        }

        return $class;
    }

    private static function generateName(string $class, string $method): string
    {
        // App\Controller\ListOrders::__invoke → app_controller_list_orders
        // App\Controller\OrderController::show → app_controller_order_controller_show
        $name = str_replace('\\', '_', $class);
        $name = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $name));
        if ($method !== '__invoke') {
            $name .= '_' . strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $method));
        }

        return ltrim($name, '_');
    }
}
