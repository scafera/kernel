<?php

declare(strict_types=1);

namespace Scafera\Kernel;

final class Bootstrap
{
    public static function init(string $projectDir): void
    {
        self::loadEnv($projectDir);

        if (!isset($_SERVER['APP_SECRET']) && !isset($_ENV['APP_SECRET'])) {
            throw new \RuntimeException(
                'APP_SECRET is not set. Define it in config/config.yaml under the "env:" section, or set it as an OS environment variable.'
            );
        }
    }

    private static function loadEnv(string $projectDir): void
    {
        // Framework defaults.
        $_SERVER['APP_ENV'] ??= $_ENV['APP_ENV'] ??= 'dev';
        $_SERVER['APP_DEBUG'] ??= $_ENV['APP_DEBUG'] ??= '1';

        self::loadEnvFromFile($projectDir . '/config/config.yaml');
        self::loadEnvFromFile($projectDir . '/config/config.local.yaml');
    }

    private static function loadEnvFromFile(string $file): void
    {
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
                $value = self::stripInlineComment($m[2]);
                $value = trim($value, " '\"");

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

    private static function stripInlineComment(string $value): string
    {
        $trimmed = ltrim($value);

        // Quoted values: skip past the closing quote, then strip comments after it.
        if (isset($trimmed[0]) && ($trimmed[0] === '"' || $trimmed[0] === "'")) {
            $quote = $trimmed[0];
            $end = strpos($trimmed, $quote, 1);
            if ($end !== false) {
                $after = substr($trimmed, $end + 1);
                $after = preg_replace('/\s+#.*$/', '', $after);
                return substr($trimmed, 0, $end + 1) . $after;
            }
        }

        // Unquoted values: only strip # when preceded by whitespace.
        return preg_replace('/\s+#.*$/', '', $value);
    }
}
