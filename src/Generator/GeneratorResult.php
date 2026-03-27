<?php

declare(strict_types=1);

namespace Scafera\Kernel\Generator;

final class GeneratorResult
{
    /**
     * @param list<string> $filesCreated Relative paths of created files.
     * @param list<string> $messages     Informational messages.
     */
    public function __construct(
        public readonly array $filesCreated = [],
        public readonly array $messages = [],
    ) {
    }
}
