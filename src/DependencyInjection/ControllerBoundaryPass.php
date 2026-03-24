<?php

declare(strict_types=1);

namespace Scafera\Kernel\DependencyInjection;

use Scafera\Kernel\Contract\ArchitecturePackageInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal Enforces that controllers do not import Symfony types directly.
 *
 * Runs during container compilation (cache:warmup). If a controller file
 * contains `use Symfony\` imports or extends AbstractController, the
 * container fails to compile with a clear error message.
 */
final class ControllerBoundaryPass implements CompilerPassInterface
{
    private const FORBIDDEN_PATTERNS = [
        '/^use\s+Symfony\\\\/m' => 'imports a Symfony class',
        '/extends\s+AbstractController/m' => 'extends Symfony\'s AbstractController',
    ];

    public function __construct(
        private readonly string $projectDir,
        private readonly ?ArchitecturePackageInterface $architecture,
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        if ($this->architecture === null) {
            return;
        }

        $violations = [];

        foreach ($this->architecture->getControllerPaths() as $path) {
            $dir = $this->projectDir . '/' . $path;
            if (!is_dir($dir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $fileViolations = $this->checkFile($file->getPathname());
                if (!empty($fileViolations)) {
                    $violations[] = $fileViolations;
                }
            }
        }

        if (!empty($violations)) {
            throw new \LogicException(
                "Scafera boundary violation: controllers must not use Symfony types directly.\n\n"
                . implode("\n", array_merge(...$violations))
                . "\n\nUse Scafera\\Kernel\\Http types instead. See ADR-018.",
            );
        }
    }

    /** @return list<string> */
    private function checkFile(string $filePath): array
    {
        $contents = file_get_contents($filePath);
        $relative = str_replace($this->projectDir . '/', '', $filePath);
        $violations = [];

        foreach (self::FORBIDDEN_PATTERNS as $pattern => $description) {
            if (preg_match($pattern, $contents)) {
                $violations[] = "  - {$relative}: {$description}";
            }
        }

        return $violations;
    }
}
