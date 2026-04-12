<?php

declare(strict_types=1);

namespace Scafera\Kernel\Tests\Fixtures\Controller;

use Scafera\Kernel\Http\Route;

#[Route('/home', methods: 'GET')]
class InvokableController
{
    public function __invoke(): string
    {
        return 'home';
    }
}
