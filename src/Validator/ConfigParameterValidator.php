<?php

declare(strict_types=1);

namespace Scafera\Kernel\Validator;

use Scafera\Kernel\Contract\ValidatorInterface;

final class ConfigParameterValidator implements ValidatorInterface
{
    public function getName(): string
    {
        return 'Config parameter references';
    }

    public function validate(string $projectDir): array
    {
        $definedParams = $this->parseDefinedParameters($projectDir);
        $usages = $this->findConfigUsages($projectDir . '/src');

        if (empty($usages)) {
            return [];
        }

        $violations = [];

        foreach ($usages as $usage) {
            if (str_starts_with($usage['param'], 'env.')) {
                continue;
            }

            if (!in_array($usage['param'], $definedParams, true)) {
                $violations[] = sprintf(
                    "%s — #[Config('%s')] references undefined parameter. Define it in config/config.yaml under 'parameters:'.",
                    $usage['file'],
                    $usage['param'],
                );
            }
        }

        return $violations;
    }

    /** @return list<string> */
    private function parseDefinedParameters(string $projectDir): array
    {
        $configFile = $projectDir . '/config/config.yaml';

        if (!is_file($configFile)) {
            return [];
        }

        $lines = file($configFile, FILE_IGNORE_NEW_LINES);
        $params = [];
        $inParams = false;

        foreach ($lines as $line) {
            $stripped = ltrim($line);

            if ($stripped === 'parameters:') {
                $inParams = true;
                continue;
            }

            if ($inParams) {
                if ($stripped === '' || $stripped[0] === '#') {
                    continue;
                }

                if ($stripped === $line && $stripped !== '') {
                    $inParams = false;
                    continue;
                }

                if (preg_match('/^\s+([a-zA-Z0-9_.]+)\s*:/', $line, $m)) {
                    $params[] = $m[1];
                }
            }
        }

        return $params;
    }

    /**
     * @return list<array{file: string, param: string}>
     */
    private function findConfigUsages(string $srcDir): array
    {
        if (!is_dir($srcDir)) {
            return [];
        }

        $usages = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($srcDir, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            if (preg_match_all('/#\[Config\([\'"]([^\'"]+)[\'"]\)\]/', $contents, $matches)) {
                $relative = str_contains($file->getPathname(), '/src/')
                    ? 'src/' . explode('/src/', $file->getPathname(), 2)[1]
                    : basename($file->getPathname());

                foreach ($matches[1] as $param) {
                    $usages[] = ['file' => $relative, 'param' => $param];
                }
            }
        }

        return $usages;
    }
}
