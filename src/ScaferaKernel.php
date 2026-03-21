<?php

declare(strict_types=1);

namespace Scafera\Kernel;

use Scafera\Kernel\Contract\ArchitecturePackageInterface;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class ScaferaKernel extends BaseKernel
{
    use MicroKernelTrait;

    private string $projectDir;
    private ?ArchitecturePackageInterface $architecturePackage = null;
    private bool $architectureResolved = false;

    public function __construct(string $environment, bool $debug, string $projectDir)
    {
        $this->projectDir = $projectDir;
        parent::__construct($environment, $debug);
    }

    public function getProjectDir(): string
    {
        return $this->projectDir;
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();

        $installed = InstalledPackages::get($this->getProjectDir());

        foreach ($installed['bundles'] as $class) {
            if (class_exists($class)) {
                yield new $class();
            }
        }
    }

    protected function configureContainer(ContainerConfigurator $c): void
    {
        $c->extension('framework', [
            'secret' => '%env(APP_SECRET)%',
        ]);

        if ('test' === $this->environment) {
            $c->extension('framework', ['test' => true]);
        }

        $c->services()->defaults()->autowire()->autoconfigure()
            ->bind('string $projectDir', $this->getProjectDir())
            ->load('Scafera\\Kernel\\Service\\', dirname(__DIR__) . '/src/Service/');

        $this->loadArchitectureServices($c);

        $this->loadOverrides($c);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $architecture = $this->getArchitecturePackage();
        if ($architecture) {
            foreach ($architecture->getControllerPaths() as $path) {
                $routes->import($this->getProjectDir() . '/' . $path, 'attribute');
            }
        }
    }

    private function loadArchitectureServices(ContainerConfigurator $c): void
    {
        $architecture = $this->getArchitecturePackage();
        if (!$architecture) {
            return;
        }

        $projectDir = $this->getProjectDir();
        $discovery = $architecture->getServiceDiscovery($projectDir);
        $services = $c->services()->defaults()->autowire()->autoconfigure();
        $services->load($discovery['namespace'], $projectDir . '/' . $discovery['resource'])
            ->exclude(array_map(fn($e) => $projectDir . '/' . $e, $discovery['exclude']));
    }

    private function loadOverrides(ContainerConfigurator $c): void
    {
        $file = $this->getProjectDir() . '/config/overrides.yaml';
        if (!is_file($file)) {
            return;
        }

        $config = Yaml::parseFile($file);
        if (!$config) {
            return;
        }
        unset($config['env']);

        if (isset($config['parameters'])) {
            foreach ($config['parameters'] as $key => $value) {
                $c->parameters()->set($key, $value);
            }
            unset($config['parameters']);
        }

        foreach ($config as $extension => $values) {
            $c->extension($extension, $values ?? []);
        }
    }

    private function getArchitecturePackage(): ?ArchitecturePackageInterface
    {
        if ($this->architectureResolved) {
            return $this->architecturePackage;
        }

        $this->architectureResolved = true;
        $installed = InstalledPackages::get($this->getProjectDir());
        $class = $installed['architecture'];

        if ($class && class_exists($class) && is_subclass_of($class, ArchitecturePackageInterface::class)) {
            $this->architecturePackage = new $class();
        }

        return $this->architecturePackage;
    }
}
