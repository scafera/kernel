<?php

declare(strict_types=1);

namespace Scafera\Kernel\Tests\Fixtures\Controller;

use Scafera\Kernel\Http\Route;

class MethodRoutesController
{
    #[Route('/orders', methods: 'GET')]
    public function list(): string
    {
        return 'list';
    }

    #[Route('/orders/{id}', methods: 'GET', requirements: ['id' => '\d+'])]
    public function show(): string
    {
        return 'show';
    }
}
