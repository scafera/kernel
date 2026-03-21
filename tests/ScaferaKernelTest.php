<?php

declare(strict_types=1);

namespace Scafera\Kernel\Tests;

use PHPUnit\Framework\TestCase;
use Scafera\Kernel\ScaferaKernel;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

class ScaferaKernelTest extends TestCase
{
    public function testConstructorSetsProjectDir(): void
    {
        $kernel = new ScaferaKernel('test', true, '/some/path');

        $this->assertSame('/some/path', $kernel->getProjectDir());
    }

    public function testRegisterBundlesYieldsFrameworkBundleFirst(): void
    {
        $kernel = new ScaferaKernel('test', true, __DIR__);
        $bundles = iterator_to_array($kernel->registerBundles());

        $this->assertNotEmpty($bundles);
        $this->assertInstanceOf(FrameworkBundle::class, $bundles[0]);
    }
}
