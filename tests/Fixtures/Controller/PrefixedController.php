<?php

declare(strict_types=1);

namespace Scafera\Kernel\Tests\Fixtures\Controller;

use Scafera\Kernel\Http\Route;

#[Route('/api/products', defaults: ['_format' => 'json'])]
class PrefixedController
{
    #[Route('', methods: 'GET')]
    public function list(): string
    {
        return 'list';
    }

    #[Route('/{id}', methods: ['GET', 'HEAD'])]
    public function show(): string
    {
        return 'show';
    }
}
