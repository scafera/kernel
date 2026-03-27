<?php

declare(strict_types=1);

namespace Scafera\Kernel\Generator;

final class FileWriter
{
    /**
     * Write content to a file, creating directories as needed.
     *
     * @return string The relative path written.
     */
    public function write(string $projectDir, string $relativePath, string $content): string
    {
        $fullPath = $projectDir . '/' . $relativePath;
        $dir = dirname($fullPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($fullPath, $content);

        return $relativePath;
    }

    public function exists(string $projectDir, string $relativePath): bool
    {
        return file_exists($projectDir . '/' . $relativePath);
    }

    /**
     * Convert a relative file path to a namespace.
     * Example: "src/Controller/Api/Status.php" → "App\Controller\Api\Status"
     */
    public function pathToNamespace(string $relativePath): string
    {
        $path = preg_replace('#^src/#', '', $relativePath);
        $path = preg_replace('#\.php$#', '', $path);

        return 'App\\' . str_replace('/', '\\', $path);
    }

    /**
     * Convert a namespace to a relative file path.
     * Example: "App\Controller\Api\Status" → "src/Controller/Api/Status.php"
     */
    public function classToPath(string $className): string
    {
        $relative = preg_replace('#^App\\\\#', '', $className);

        return 'src/' . str_replace('\\', '/', $relative) . '.php';
    }

    /**
     * Convert a string to PascalCase.
     * Example: "order-list" → "OrderList", "order_list" → "OrderList"
     */
    public function toPascalCase(string $name): string
    {
        return str_replace(['-', '_', ' '], '', ucwords($name, '-_ '));
    }
}
