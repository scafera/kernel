<?php

declare(strict_types=1);

namespace Scafera\Kernel\Console\Internal;

use Scafera\Kernel\Console\Attribute\AsCommand;
use Scafera\Kernel\Console\Command;
use Scafera\Kernel\Console\Input;
use Scafera\Kernel\Console\Output;
use Scafera\Kernel\Contract\PathProviderInterface;
use Scafera\Kernel\InstalledPackages;

#[AsCommand('info:paths', description: 'Show all registered paths')]
class InfoPathsCommand extends Command
{
    /** @param iterable<PathProviderInterface> $pathProviders */
    public function __construct(
        private readonly string $projectDir,
        private readonly iterable $pathProviders = [],
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArg('label', 'Filter paths by label (e.g. cache, storage, entity)', required: false);
        $this->addFlag('json', null, 'Output as JSON');
    }

    protected function handle(Input $input, Output $output): int
    {
        $labelFilter = $input->argument('label');
        $jsonOutput = $input->option('json');

        $entries = $this->collectPaths();

        if ($labelFilter !== null) {
            $entries = array_values(array_filter(
                $entries,
                fn(array $entry) => $entry['label'] === $labelFilter,
            ));

            if (empty($entries) && !$jsonOutput) {
                $output->writeln("No path found for '$labelFilter'.");

                return self::SUCCESS;
            }
        }

        if ($jsonOutput) {
            $output->writeln(json_encode($entries, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->renderTable($entries, $output);

        return self::SUCCESS;
    }

    /** @return list<array{label: string, path: string, source: string}> */
    private function collectPaths(): array
    {
        $entries = [];

        $this->addKernelPaths($entries);
        $this->addArchitecturePaths($entries);
        $this->addProviderPaths($entries);

        return $entries;
    }

    /** @param list<array{label: string, path: string, source: string}> $entries */
    private function addKernelPaths(array &$entries): void
    {
        $entries[] = ['label' => 'config', 'path' => 'config/', 'source' => 'kernel'];
        $entries[] = ['label' => 'cache', 'path' => 'var/cache/', 'source' => 'kernel'];
        $entries[] = ['label' => 'public', 'path' => 'public/', 'source' => 'kernel'];
        $entries[] = ['label' => 'var', 'path' => 'var/', 'source' => 'kernel'];
    }

    /** @param list<array{label: string, path: string, source: string}> $entries */
    private function addArchitecturePaths(array &$entries): void
    {
        $architecture = InstalledPackages::resolveArchitecture($this->projectDir);
        if ($architecture === null) {
            return;
        }

        $source = $architecture->getName();

        foreach ($architecture->getStructure() as $path => $description) {
            $label = $this->pathToLabel($path);
            $entries[] = ['label' => $label, 'path' => rtrim($path, '/') . '/', 'source' => $source];
        }

        $entityMapping = $architecture->getEntityMapping();
        if ($entityMapping !== null) {
            $entries[] = ['label' => 'entity-mapping', 'path' => rtrim($entityMapping['dir'], '/') . '/', 'source' => $source];
        }

        $translations = $architecture->getTranslationsDir();
        if ($translations !== null) {
            $entries[] = ['label' => 'translations', 'path' => rtrim($translations, '/') . '/', 'source' => $source];
        }

        $storage = $architecture->getStorageDir();
        if ($storage !== null) {
            $entries[] = ['label' => 'storage', 'path' => rtrim($storage, '/') . '/', 'source' => $source];
        }

        $assets = $architecture->getAssetsDir();
        if ($assets !== null) {
            $entries[] = ['label' => 'assets', 'path' => rtrim($assets, '/') . '/', 'source' => $source];
        }
    }

    /** @param list<array{label: string, path: string, source: string}> $entries */
    private function addProviderPaths(array &$entries): void
    {
        foreach ($this->pathProviders as $provider) {
            $source = $provider->getSourceName();
            foreach ($provider->getPaths() as $path) {
                $entries[] = ['label' => $path['label'], 'path' => rtrim($path['path'], '/') . '/', 'source' => $source];
            }
        }
    }

    /** @param list<array{label: string, path: string, source: string}> $entries */
    private function renderTable(array $entries, Output $output): void
    {
        $labelWidth = 4; // minimum: "LABEL" minus 1
        $pathWidth = 4;

        foreach ($entries as $entry) {
            $labelWidth = max($labelWidth, strlen($entry['label']));
            $pathWidth = max($pathWidth, strlen($entry['path']));
        }

        $labelWidth += 2;
        $pathWidth += 2;

        $header = str_pad('LABEL', $labelWidth) . str_pad('PATH', $pathWidth) . 'SOURCE';
        $output->writeln($header);

        foreach ($entries as $entry) {
            $line = str_pad($entry['label'], $labelWidth) . str_pad($entry['path'], $pathWidth) . $entry['source'];
            $output->writeln($line);
        }
    }

    private function pathToLabel(string $path): string
    {
        $parts = explode('/', trim($path, '/'));

        if (count($parts) === 1) {
            return strtolower($parts[0]);
        }

        // e.g. "tests/Controller" → "controller-tests", "src/Service" → "service"
        $last = strtolower(end($parts));
        $first = strtolower($parts[0]);

        if ($first === 'tests') {
            return $last . '-tests';
        }

        return $last;
    }
}
