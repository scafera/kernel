<?php

declare(strict_types=1);

namespace Scafera\Kernel;

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
        $architecture = null;

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

            if (!$architecture && isset($pkg['extra']['scafera-architecture'])) {
                $architecture = $pkg['extra']['scafera-architecture'];
            }
        }

        return ['bundles' => $bundles, 'architecture' => $architecture];
    }

    private static function writeCache(string $cachePath, array $result): void
    {
        $dir = dirname($cachePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($cachePath, '<?php return ' . var_export($result, true) . ';' . "\n");
    }
}
