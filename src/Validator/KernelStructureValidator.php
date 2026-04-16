<?php

declare(strict_types=1);

namespace Scafera\Kernel\Validator;

use Scafera\Kernel\Contract\ValidatorInterface;

final class KernelStructureValidator implements ValidatorInterface
{
    public const REQUIRED = [
        'composer.json' => 'file',
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
        'cache' => [
            'type' => 'dir',
            'reason' => "'cache/' at project root is not allowed — Scafera stores cache under 'var/cache/'.",
        ],
        'log' => [
            'type' => 'dir',
            'reason' => "'log/' at project root is not allowed — Scafera stores logs under 'var/log/'.",
        ],
        'logs' => [
            'type' => 'dir',
            'reason' => "'logs/' at project root is not allowed — Scafera stores logs under 'var/log/'.",
        ],
        'storage' => [
            'type' => 'dir',
            'reason' => "'storage/' at project root is not allowed — user-generated files belong under 'var/uploads/'.",
        ],
        'uploads' => [
            'type' => 'dir',
            'reason' => "'uploads/' at project root is not allowed — user-generated files belong under 'var/uploads/'.",
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
