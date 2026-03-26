<?php

declare(strict_types=1);

namespace Scafera\Kernel\Advisor;

use Scafera\Kernel\Contract\AdvisorInterface;

final class UploadLocationAdvisor implements AdvisorInterface
{
    private const SUSPICIOUS_PATHS = [
        'storage' => "Found 'storage/' directory. Consider using 'var/upload/' for file storage instead.",
        'public/uploads' => "Found 'public/uploads/' directory. Consider using 'var/upload/' for file storage instead.",
        'var/uploads' => "Found 'var/uploads/' (plural). The Scafera convention is 'var/upload/' (singular).",
    ];

    public function getName(): string
    {
        return 'Upload location';
    }

    public function skipped(string $projectDir): ?string
    {
        foreach (self::SUSPICIOUS_PATHS as $path => $hint) {
            if (is_dir($projectDir . '/' . $path)) {
                return null;
            }
        }

        return 'no upload directories detected';
    }

    public function advise(string $projectDir): array
    {
        $hints = [];

        foreach (self::SUSPICIOUS_PATHS as $path => $hint) {
            if (is_dir($projectDir . '/' . $path)) {
                $hints[] = $hint;
            }
        }

        return $hints;
    }
}
