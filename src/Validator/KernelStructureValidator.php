<?php

declare(strict_types=1);

namespace Scafera\Kernel\Validator;

use Scafera\Kernel\Contract\ValidatorInterface;

final class KernelStructureValidator implements ValidatorInterface
{
    private const REQUIRED = [
        'composer.json' => 'file',
        'src' => 'dir',
        'public' => 'dir',
        'var' => 'dir',
    ];

    public const FORBIDDEN = [
        'config/packages' => [
            'type' => 'dir',
            'reason' => "Scafera does not scan 'config/packages/'. Use 'config/config.yaml' instead.",
        ],
        'src/Kernel.php' => [
            'type' => 'file',
            'reason' => 'Scafera provides its own kernel. Remove src/Kernel.php.',
        ],
        'public/index.php' => [
            'type' => 'file',
            'reason' => 'Scafera provides the front controller. Remove public/index.php.',
        ],
    ];

    public function getName(): string
    {
        return 'Kernel structure';
    }

    public function validate(string $projectDir): array
    {
        $violations = [];

        foreach (self::REQUIRED as $path => $type) {
            $full = $projectDir . '/' . $path;

            if ($type === 'file' && !is_file($full)) {
                $violations[] = "Required file '{$path}' does not exist.";
            } elseif ($type === 'dir' && !is_dir($full)) {
                $violations[] = "Required directory '{$path}/' does not exist.";
            }
        }

        foreach (self::FORBIDDEN as $path => $rule) {
            $full = $projectDir . '/' . $path;
            $exists = $rule['type'] === 'file' ? is_file($full) : is_dir($full);

            if ($exists) {
                $violations[] = $rule['reason'];
            }
        }

        return $violations;
    }
}
