<?php

declare(strict_types=1);

namespace Scafera\Kernel;

use Scafera\Kernel\Contract\ArchitecturePackageInterface;

class InstalledPackages
{
    public static function get(string $projectDir): array
    {
        $cachePath = $projectDir . '/var/cache/installed_packages.php';

        if (is_file($cachePath)) {
            return require $cachePath;
        }

        $result = self::parse($projectDir);
        self::writeCache($cachePath, $result);

        return $result;
    }

    private static function parse(string $projectDir): array
    {
        $file = $projectDir . '/vendor/composer/installed.json';
        if (!is_file($file)) {
            return ['bundles' => [], 'architecture' => null];
        }

        $installed = json_decode(file_get_contents($file), true);
        if (!is_array($installed)) {
            return ['bundles' => [], 'architecture' => null];
        }
        $bundles = [];
        $architecturePackages = [];

        foreach ($installed['packages'] ?? [] as $pkg) {
            if (($pkg['type'] ?? '') === 'symfony-bundle' && $pkg['name'] !== 'symfony/framework-bundle') {
                $pkgDir = realpath($projectDir . '/vendor/composer/' . ($pkg['install-path'] ?? ''));
                if ($pkgDir) {
                    foreach ($pkg['autoload']['psr-4'] ?? [] as $ns => $path) {
                        $searchDir = rtrim($pkgDir . '/' . $path, '/');
                        foreach (glob($searchDir . '/*Bundle.php') as $file) {
                            $bundles[] = $ns . basename($file, '.php');
                        }
                    }
                }
            }

            if (isset($pkg['extra']['scafera-architecture'])) {
                $architecturePackages[$pkg['name']] = $pkg['extra']['scafera-architecture'];
            }
        }

        if (\count($architecturePackages) > 1) {
            throw new \RuntimeException(sprintf(
                'Multiple Scafera architecture packages detected: %s. Only one architecture package may be installed at a time.',
                implode(', ', array_keys($architecturePackages))
            ));
        }

        $architecture = $architecturePackages ? reset($architecturePackages) : null;

        return ['bundles' => $bundles, 'architecture' => $architecture];
    }

    public static function resolveArchitecture(string $projectDir): ?ArchitecturePackageInterface
    {
        $installed = self::get($projectDir);
        $class = $installed['architecture'];

        if ($class && class_exists($class) && is_subclass_of($class, ArchitecturePackageInterface::class)) {
            return new $class();
        }

        return null;
    }

    private static function writeCache(string $cachePath, array $result): void
    {
        $dir = dirname($cachePath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new \RuntimeException(sprintf('Unable to create cache directory: %s', $dir));
        }

        if (file_put_contents($cachePath, '<?php return ' . var_export($result, true) . ';' . "\n") === false) {
            throw new \RuntimeException(sprintf('Unable to write cache file: %s', $cachePath));
        }
    }
}
