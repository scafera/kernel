<?php

declare(strict_types=1);

namespace Scafera\Kernel\Console\Internal;

use Scafera\Kernel\Console\Attribute\AsCommand;
use Scafera\Kernel\Console\Command;
use Scafera\Kernel\Console\Input;
use Scafera\Kernel\Console\Output;
use Scafera\Kernel\Contract\AdvisorInterface;
use Scafera\Kernel\Contract\ArchitecturePackageInterface;
use Scafera\Kernel\Contract\ValidatorInterface;
use Scafera\Kernel\InstalledPackages;
use Scafera\Kernel\Validator\ConfigParameterValidator;
use Scafera\Kernel\Validator\KernelStructureValidator;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

#[AsCommand('validate', description: 'Validate project structure against architecture rules')]
class ValidateCommand extends Command
{
    /**
     * @param iterable<ValidatorInterface> $packageValidators
     * @param iterable<AdvisorInterface> $packageAdvisors
     */
    public function __construct(
        private readonly string $projectDir,
        private readonly iterable $packageValidators = [],
        private readonly iterable $packageAdvisors = [],
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addFlag('strict', null, 'Run all rules regardless of project/architecture ignore lists');
    }

    protected function handle(Input $input, Output $output): int
    {
        $output->writeln('<comment>Scafera Structure Validation</comment>');
        $output->writeln('');

        $strict = (bool) $input->option('strict');
        $architecture = InstalledPackages::resolveArchitecture($this->projectDir);
        $ignoreSet = $this->buildIgnoreSet($architecture, $output);
        $effectiveIgnores = $strict ? [] : $ignoreSet;

        /** @var list<array{id: string, name: string}> $ignored */
        $ignored = [];
        /** @var list<string> $seenIds */
        $seenIds = [];

        // Phase 1: Kernel checks (always run)
        $output->writeln('<info>Kernel checks:</info>');
        [$kernelPassed, $kernelFailed, $kernelViolations] = $this->runValidatorInstances(
            $this->getKernelValidators(),
            $output,
            $effectiveIgnores,
            $ignored,
            $seenIds,
        );
        $this->runAdvisors($this->getKernelAdvisors(), $output, $effectiveIgnores, $ignored, $seenIds);

        // Phase 2: Architecture checks (if installed)
        $archPassed = 0;
        $archFailed = 0;
        $archViolations = 0;

        if ($architecture !== null) {
            $output->writeln('');
            $output->writeln('<info>' . $architecture->getName() . ' checks:</info>');
            [$archPassed, $archFailed, $archViolations] = $this->runValidatorInstances(
                $architecture->getValidators(),
                $output,
                $effectiveIgnores,
                $ignored,
                $seenIds,
            );
            $this->runAdvisors($architecture->getAdvisors(), $output, $effectiveIgnores, $ignored, $seenIds);
        }

        // Phase 3: Capability package checks (tagged validators)
        $pkgPassed = 0;
        $pkgFailed = 0;
        $pkgViolations = 0;
        $packageValidatorList = iterator_to_array($this->packageValidators);

        $packageAdvisorList = iterator_to_array($this->packageAdvisors);

        if (!empty($packageValidatorList) || !empty($packageAdvisorList)) {
            $output->writeln('');
            $output->writeln('<info>Package checks:</info>');

            if (!empty($packageValidatorList)) {
                [$pkgPassed, $pkgFailed, $pkgViolations] = $this->runValidatorInstances(
                    $packageValidatorList,
                    $output,
                    $effectiveIgnores,
                    $ignored,
                    $seenIds,
                );
            }

            $this->runAdvisors($packageAdvisorList, $output, $effectiveIgnores, $ignored, $seenIds);
        }

        // Ignored block (grouped at the end)
        if (!empty($ignored)) {
            $output->writeln('');
            $output->writeln('<info>Ignored:</info>');
            foreach ($ignored as $entry) {
                $output->writeln(sprintf('  <fg=yellow>[IGNORED]</> %s (%s)', $entry['name'], $entry['id']));
            }
        }

        // Unknown ignore IDs (typo protection)
        $unknownIgnores = array_values(array_diff($ignoreSet, $seenIds));
        if (!empty($unknownIgnores)) {
            $output->writeln('');
            $output->writeln('<comment>Warning: unknown ignore ID(s) — check for typos:</comment>');
            foreach ($unknownIgnores as $id) {
                $output->writeln('  - ' . $id);
            }
        }

        // Summary
        $totalPassed = $kernelPassed + $archPassed + $pkgPassed;
        $totalFailed = $kernelFailed + $archFailed + $pkgFailed;
        $totalViolations = $kernelViolations + $archViolations + $pkgViolations;
        $ignoredCount = count($ignored);

        $output->writeln('');

        if ($totalFailed === 0) {
            $summary = $totalPassed . ' checks passed.';
            if ($ignoredCount > 0) {
                $summary .= ' ' . $ignoredCount . ' rule(s) ignored.';
            }
            $output->success($summary);

            return self::SUCCESS;
        }

        $summary = $totalFailed . ' check(s) failed, ' . $totalViolations . ' violation(s) found.';
        if ($ignoredCount > 0) {
            $summary .= ' ' . $ignoredCount . ' rule(s) ignored.';
        }
        $output->error($summary);

        return self::FAILURE;
    }

    /**
     * @param list<AdvisorInterface> $advisors
     * @param list<string> $ignoreSet
     * @param list<array{id: string, name: string}> $ignored
     * @param list<string> $seenIds
     */
    private function runAdvisors(array $advisors, Output $output, array $ignoreSet, array &$ignored, array &$seenIds): void
    {
        if (empty($advisors)) {
            return;
        }

        $output->writeln('');

        foreach ($advisors as $advisor) {
            $seenIds[] = $advisor->getId();

            if (in_array($advisor->getId(), $ignoreSet, true)) {
                $ignored[] = ['id' => $advisor->getId(), 'name' => $advisor->getName()];
                continue;
            }

            if ($advisor->skipped($this->projectDir) !== null) {
                continue;
            }

            $hints = $advisor->advise($this->projectDir);

            if (empty($hints)) {
                $output->writeln('  <fg=blue>ℹ</> ' . $advisor->getName() . ' <fg=blue>ok</>');
            } else {
                $output->writeln('  <fg=blue>ℹ</> ' . $advisor->getName());
                foreach ($hints as $hint) {
                    $output->writeln('    · ' . $hint);
                }
            }
        }
    }

    /**
     * @param list<ValidatorInterface> $validators
     * @param list<string> $ignoreSet
     * @param list<array{id: string, name: string}> $ignored
     * @param list<string> $seenIds
     * @return array{int, int, int} [passed, failed, violations]
     */
    private function runValidatorInstances(array $validators, Output $output, array $ignoreSet, array &$ignored, array &$seenIds): array
    {
        $passed = 0;
        $failed = 0;
        $totalViolations = 0;

        foreach ($validators as $validator) {
            $seenIds[] = $validator->getId();

            if (in_array($validator->getId(), $ignoreSet, true)) {
                $ignored[] = ['id' => $validator->getId(), 'name' => $validator->getName()];
                continue;
            }

            $violations = $validator->validate($this->projectDir);

            if (empty($violations)) {
                $output->writeln('  <fg=green>✓</> ' . $validator->getName());
                $passed++;
            } else {
                $output->writeln('  <fg=red>✗</> ' . $validator->getName() . ' <fg=red>FAILED</>');
                foreach ($violations as $violation) {
                    $output->writeln('    - ' . $violation);
                }
                $output->writeln('');
                $failed++;
                $totalViolations += count($violations);
            }
        }

        return [$passed, $failed, $totalViolations];
    }

    /** @return list<ValidatorInterface> */
    private function getKernelValidators(): array
    {
        return [
            new KernelStructureValidator(),
            new ConfigParameterValidator(),
        ];
    }

    /** @return list<AdvisorInterface> */
    private function getKernelAdvisors(): array
    {
        return [];
    }

    /**
     * Union of architecture-level and project-level ignore lists, deduped.
     *
     * @return list<string>
     */
    private function buildIgnoreSet(?ArchitecturePackageInterface $architecture, Output $output): array
    {
        $ignores = $architecture?->getIgnoredRules() ?? [];

        $configFile = $this->projectDir . '/config/config.yaml';
        if (is_file($configFile)) {
            try {
                $config = Yaml::parseFile($configFile);
            } catch (ParseException $e) {
                $output->writeln(sprintf(
                    '<comment>Warning: config/config.yaml is invalid YAML (%s). Ignore list from config not applied.</comment>',
                    $e->getMessage(),
                ));
                $output->writeln('');

                return array_values(array_unique($ignores));
            }

            $projectIgnores = $config['scafera']['ignore'] ?? [];

            if (is_array($projectIgnores)) {
                foreach ($projectIgnores as $id) {
                    if (is_string($id)) {
                        $ignores[] = $id;
                    }
                }
            }
        }

        return array_values(array_unique($ignores));
    }
}
