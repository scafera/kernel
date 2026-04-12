<?php

declare(strict_types=1);

namespace Scafera\Kernel\Tests\Fixtures\Controller;

class NoRouteController
{
    public function index(): string
    {
        return 'no route';
    }
}
