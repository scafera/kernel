<?php

declare(strict_types=1);

namespace Scafera\Kernel;

class Bootstrap
{
    public static function init(string $projectDir): void
    {
        self::loadEnv($projectDir);

        if (!isset($_SERVER['APP_SECRET']) && !isset($_ENV['APP_SECRET'])) {
            throw new \RuntimeException(
                'APP_SECRET is not set. Define it in config/overrides.yaml under the "env:" section, or set it as an OS environment variable.'
            );
        }
    }

    private static function loadEnv(string $projectDir): void
    {
        // Framework defaults.
        $_SERVER['APP_ENV'] ??= $_ENV['APP_ENV'] ??= 'dev';
        $_SERVER['APP_DEBUG'] ??= $_ENV['APP_DEBUG'] ??= '1';

        // Override defaults with overrides.yaml env: section (if present).
        $file = $projectDir . '/config/overrides.yaml';
        if (!is_file($file)) {
            return;
        }

        $inEnvSection = false;
        foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
            if ($line === 'env:') {
                $inEnvSection = true;
                continue;
            }

            if ($inEnvSection && preg_match('/^\s+(\w+):\s*(.+)$/', $line, $m)) {
                $key = $m[1];
                $value = trim($m[2], " '\"");

                // Real OS env vars always win.
                if (getenv($key) !== false) {
                    continue;
                }

                $_SERVER[$key] = $value;
                $_ENV[$key] = $value;
            } elseif ($inEnvSection && !ctype_space($line[0] ?? '') && $line !== '') {
                break;
            }
        }
    }
}
