<?php

declare(strict_types=1);

namespace Scafera\Kernel\Attribute;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
class Config extends Autowire
{
    public function __construct(string $key)
    {
        if (str_starts_with($key, 'env.')) {
            parent::__construct(env: substr($key, 4));
        } else {
            parent::__construct(param: $key);
        }
    }
}
