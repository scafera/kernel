<?php

declare(strict_types=1);

namespace Scafera\Kernel;

use Scafera\Kernel\Contract\ArchitecturePackageInterface;
use Scafera\Kernel\Console\Internal\MakeCommand;
use Scafera\Kernel\Console\Internal\ValidateCommand;
use Scafera\Kernel\DependencyInjection\ControllerBoundaryPass;
use Scafera\Kernel\Validator\KernelStructureValidator;
use Scafera\Kernel\Http\Internal\RequestResolver;
use Scafera\Kernel\Http\Internal\ResponseListener;
use Scafera\Kernel\Http\Internal\RouteLoader;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
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

    public function __construct(string $environment, bool $debug, ?string $projectDir = null)
    {
        $this->projectDir = $projectDir ?? $_SERVER['KERNEL_PROJECT_DIR'] ?? getcwd();
        parent::__construct($environment, $debug);
    }

    public function getProjectDir(): string
    {
        return $this->projectDir;
    }

    protected function build(ContainerBuilder $container): void
    {
        $this->checkForbiddenStructure();

        $container->addCompilerPass(
            new ControllerBoundaryPass($this->projectDir, $this->getArchitecturePackage()),
        );
    }

    private function checkForbiddenStructure(): void
    {
        $violations = [];

        foreach (KernelStructureValidator::FORBIDDEN as $path => $rule) {
            $full = $this->projectDir . '/' . $path;
            $exists = $rule['type'] === 'file' ? is_file($full) : is_dir($full);

            if ($exists) {
                $violations[] = '  - ' . $path . ': ' . $rule['reason'];
            }
        }

        if (!empty($violations)) {
            throw new \LogicException(
                "Scafera structure violation: forbidden files or directories detected.\n\n"
                . implode("\n", $violations)
                . "\n\nRemove these before continuing.",
            );
        }
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

        $this->registerHttpInfrastructure($c);

        $this->loadArchitectureServices($c);

        $this->loadOverrides($c);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $architecture = $this->getArchitecturePackage();
        if (!$architecture) {
            return;
        }

        foreach ($architecture->getControllerPaths() as $path) {
            $dir = $this->getProjectDir() . '/' . $path;
            foreach (RouteLoader::loadFromDirectory($dir) as $name => $route) {
                $entry = $routes->add($name, $route['path'])
                    ->controller($route['controller'])
                    ->defaults($route['defaults']);

                if (!empty($route['methods'])) {
                    $entry->methods($route['methods']);
                }
                if (!empty($route['requirements'])) {
                    $entry->requirements($route['requirements']);
                }
            }
        }
    }

    private function registerHttpInfrastructure(ContainerConfigurator $c): void
    {
        $c->services()
            ->set(RequestResolver::class)
                ->autowire()
                ->tag('controller.argument_value_resolver', ['priority' => 10])
            ->set(ResponseListener::class)
                ->tag('kernel.event_subscriber');

        $c->services()
            ->set(ValidateCommand::class)
                ->args([
                    $this->getProjectDir(),
                ])
                ->tag('console.command')
            ->set(MakeCommand::class)
                ->args([
                    $this->getProjectDir(),
                ])
                ->tag('console.command');
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

        // Tag controller services so Symfony can resolve their dependencies.
        foreach ($architecture->getControllerPaths() as $path) {
            $ns = $this->pathToNamespace($discovery['namespace'], $discovery['resource'], $path);
            if ($ns !== null) {
                $c->services()->defaults()->autowire()->autoconfigure()
                    ->load($ns, $projectDir . '/' . $path)
                    ->tag('controller.service_arguments');
            }
        }
    }

    private function pathToNamespace(string $rootNamespace, string $rootResource, string $controllerPath): ?string
    {
        $rootResource = rtrim($rootResource, '/') . '/';
        $controllerPath = rtrim($controllerPath, '/') . '/';

        if (!str_starts_with($controllerPath, $rootResource)) {
            return null;
        }

        $relative = substr($controllerPath, strlen($rootResource));

        return $rootNamespace . str_replace('/', '\\', $relative);
    }

    private function loadOverrides(ContainerConfigurator $c): void
    {
        $this->loadOverridesFromFile($c, $this->getProjectDir() . '/config/config.yaml');
        $this->loadOverridesFromFile($c, $this->getProjectDir() . '/config/config.local.yaml');
    }

    private function loadOverridesFromFile(ContainerConfigurator $c, string $file): void
    {
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
