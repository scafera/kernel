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

#[AsCommand('validate', description: 'Validate project structure against architecture rules')]
class ValidateCommand extends Command
{
    public function __construct(
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function handle(Input $input, Output $output): int
    {
        $architecture = $this->resolveArchitecture();

        if ($architecture === null) {
            $output->warning('No architecture package installed. Nothing to validate.');

            return self::SUCCESS;
        }

        $output->writeln('<comment>Scafera Structure Validation (' . $architecture->getName() . ')</comment>');
        $output->writeln('');

        $validatorClasses = $architecture->getValidators();
        if (empty($validatorClasses)) {
            $output->info('Architecture package defines no validators.');

            return self::SUCCESS;
        }

        $passed = 0;
        $failed = 0;
        $totalViolations = 0;

        foreach ($validatorClasses as $class) {
            if (!class_exists($class) || !is_subclass_of($class, ValidatorInterface::class)) {
                $output->error('Invalid validator class: ' . $class);
                $failed++;

                continue;
            }

            /** @var ValidatorInterface $validator */
            $validator = new $class();
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

        $this->runAdvisors($architecture, $output);

        $output->writeln('');

        if ($failed === 0) {
            $output->success($passed . ' checks passed.');

            return self::SUCCESS;
        }

        $output->error($failed . ' check(s) failed, ' . $totalViolations . ' violation(s) found.');

        return self::FAILURE;
    }

    private function runAdvisors(ArchitecturePackageInterface $architecture, Output $output): void
    {
        $advisorClasses = $architecture->getAdvisors();
        if (empty($advisorClasses)) {
            return;
        }

        $output->writeln('');
        $messages = [];
        $hasOutput = false;

        foreach ($advisorClasses as $class) {
            if (!class_exists($class) || !is_subclass_of($class, AdvisorInterface::class)) {
                continue;
            }

            /** @var AdvisorInterface $advisor */
            $advisor = new $class();
            $reason = $advisor->skipped($this->projectDir);

            if ($reason !== null) {
                $output->writeln('  <fg=yellow>⊘</> ' . $advisor->getName() . ' <fg=yellow>skipped</> (' . $reason . ')');
                $hasOutput = true;

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

    private function resolveArchitecture(): ?ArchitecturePackageInterface
    {
        $installed = InstalledPackages::get($this->projectDir);
        $class = $installed['architecture'];

        if ($class && class_exists($class) && is_subclass_of($class, ArchitecturePackageInterface::class)) {
            return new $class();
        }

        return null;
    }
}
