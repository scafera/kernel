<?php

declare(strict_types=1);

namespace Scafera\Kernel\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Scafera\Kernel\Attribute\Config;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ConfigTest extends TestCase
{
    public function testExtendsSymfonyAutowire(): void
    {
        $this->assertTrue(is_subclass_of(Config::class, Autowire::class));
    }

    public function testCanBeUsedAsAttribute(): void
    {
        $ref = new \ReflectionClass(Config::class);
        $attrs = $ref->getAttributes(\Attribute::class);

        $this->assertNotEmpty($attrs);
    }

    public function testTargetsParameterAndProperty(): void
    {
        $ref = new \ReflectionClass(Config::class);
        $attr = $ref->getAttributes(\Attribute::class)[0]->newInstance();

        $this->assertNotEquals(0, $attr->flags & \Attribute::TARGET_PARAMETER);
        $this->assertNotEquals(0, $attr->flags & \Attribute::TARGET_PROPERTY);
    }

    public function testResolvesParameterKey(): void
    {
        $config = new Config('app.items_per_page');

        $this->assertInstanceOf(Autowire::class, $config);
    }

    public function testResolvesEnvPrefix(): void
    {
        $config = new Config('env.DATABASE_URL');

        $this->assertInstanceOf(Autowire::class, $config);
    }
}
