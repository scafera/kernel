<?php

declare(strict_types=1);

namespace Scafera\Kernel\Service;

use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;

class InstalledPackagesCacheClearer implements CacheClearerInterface
{
    public function __construct(private string $projectDir) {}

    public function clear(string $cacheDir): void
    {
        $file = $this->projectDir . '/var/cache/installed_packages.php';
        if (is_file($file)) {
            unlink($file);
        }
    }
}
