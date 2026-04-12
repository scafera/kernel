<?php

declare(strict_types=1);

namespace Scafera\Kernel\Tests\Fixtures\Controller;

use Scafera\Kernel\Http\Route;

#[Route('/abstract')]
abstract class AbstractBaseController
{
    #[Route('/test')]
    public function test(): string
    {
        return 'test';
    }
}
