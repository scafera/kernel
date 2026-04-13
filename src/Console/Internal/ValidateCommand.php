<?php

declare(strict_types=1);

namespace Scafera\Kernel\Console\Internal;

use Scafera\Kernel\Console\Attribute\AsCommand;
use Scafera\Kernel\Console\Command;
use Scafera\Kernel\Console\Input;
use Scafera\Kernel\Console\Output;
use Scafera\Kernel\Contract\AdvisorInterface;
use Scafera\Kernel\Contract\ValidatorInterface;
use Scafera\Kernel\InstalledPackages;
use Scafera\Kernel\Validator\ConfigParameterValidator;
use Scafera\Kernel\Validator\KernelStructureValidator;

#[AsCommand('validate', description: 'Validate project structure against architecture rules')]
class ValidateCommand extends Command
{
    /** @param iterable<ValidatorInterface> $packageValidators */
    public function __construct(
        private readonly string $projectDir,
        private readonly iterable $packageValidators = [],
    ) {
        parent::__construct();
    }

    protected function handle(Input $input, Output $output): int
    {
        $output->writeln('<comment>Scafera Structure Validation</comment>');
        $output->writeln('');

        // Phase 1: Kernel checks (always run)
        $output->writeln('<info>Kernel checks:</info>');
        [$kernelPassed, $kernelFailed, $kernelViolations] = $this->runValidatorInstances(
            $this->getKernelValidators(),
            $output,
        );
        $this->runAdvisors($this->getKernelAdvisors(), $output);

        // Phase 2: Architecture checks (if installed)
        $architecture = InstalledPackages::resolveArchitecture($this->projectDir);
        $archPassed = 0;
        $archFailed = 0;
        $archViolations = 0;

        if ($architecture !== null) {
            $output->writeln('');
            $output->writeln('<info>' . $architecture->getName() . ' checks:</info>');
            [$archPassed, $archFailed, $archViolations] = $this->runValidatorInstances(
                $architecture->getValidators(),
                $output,
            );
            $this->runAdvisors($architecture->getAdvisors(), $output);
        }

        // Phase 3: Capability package checks (tagged validators)
        $pkgPassed = 0;
        $pkgFailed = 0;
        $pkgViolations = 0;
        $packageValidatorList = iterator_to_array($this->packageValidators);

        if (!empty($packageValidatorList)) {
            $output->writeln('');
            $output->writeln('<info>Package checks:</info>');
            [$pkgPassed, $pkgFailed, $pkgViolations] = $this->runValidatorInstances(
                $packageValidatorList,
                $output,
            );
        }

        // Summary
        $totalPassed = $kernelPassed + $archPassed + $pkgPassed;
        $totalFailed = $kernelFailed + $archFailed + $pkgFailed;
        $totalViolations = $kernelViolations + $archViolations + $pkgViolations;

        $output->writeln('');

        if ($totalFailed === 0) {
            $output->success($totalPassed . ' checks passed.');

            return self::SUCCESS;
        }

        $output->error($totalFailed . ' check(s) failed, ' . $totalViolations . ' violation(s) found.');

        return self::FAILURE;
    }

    /**
     * @param list<AdvisorInterface> $advisors
     */
    private function runAdvisors(array $advisors, Output $output): void
    {
        if (empty($advisors)) {
            return;
        }

        $output->writeln('');

        foreach ($advisors as $advisor) {
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
     * @return array{int, int, int} [passed, failed, violations]
     */
    private function runValidatorInstances(array $validators, Output $output): array
    {
        $passed = 0;
        $failed = 0;
        $totalViolations = 0;

        foreach ($validators as $validator) {
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
}
