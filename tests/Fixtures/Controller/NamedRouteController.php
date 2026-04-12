<?php

declare(strict_types=1);

namespace Scafera\Kernel\Tests\Fixtures\Controller;

use Scafera\Kernel\Http\Route;

#[Route('/dashboard', name: 'dashboard', methods: 'GET')]
class NamedRouteController
{
    public function __invoke(): string
    {
        return 'dashboard';
    }
}
