<?php

declare(strict_types=1);

$projectDir = dirname(__DIR__);

require_once $projectDir . '/vendor/scafera/kernel/src/Bootstrap.php';
\Scafera\Kernel\Bootstrap::init($projectDir);

require $projectDir . '/vendor/autoload_runtime.php';

return fn(array $context) => new \Scafera\Kernel\ScaferaKernel(
    $context['APP_ENV'],
    (bool) $context['APP_DEBUG'],
    $projectDir
);
