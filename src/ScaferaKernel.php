<?php

declare(strict_types=1);

namespace Scafera\Kernel;

use Scafera\Kernel\Contract\ArchitecturePackageInterface;
use Scafera\Kernel\Console\Internal\MakeCommand;
use Symfony\Component\DependencyInjection\Definition;
use Scafera\Kernel\Console\Internal\InfoPathsCommand;
use Scafera\Kernel\Console\Internal\ValidateCommand;
use Scafera\Kernel\Contract\PathProviderInterface;
use Scafera\Kernel\DependencyInjection\ControllerBoundaryPass;
use Scafera\Kernel\Validator\KernelStructureValidator;
use Scafera\Kernel\Http\Internal\RequestResolver;
use Scafera\Kernel\Http\Internal\ResponseListener;
use Scafera\Kernel\Http\Internal\WelcomeListener;
use Scafera\Kernel\Http\Internal\RouteLoader;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\PhpConfigReferenceDumpPass;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

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
        $this->checkRequiredStructure();

        $container->addCompilerPass(
            new ControllerBoundaryPass($this->projectDir, $this->getArchitecturePackage()),
        );

        $this->registerGeneratorCommands($container);

        $container->registerForAutoconfiguration(PathProviderInterface::class)
            ->addTag('scafera.path_provider');

        $this->removeSymfonyReferenceDumpPass($container);
    }

    /**
     * Symfony's FrameworkBundle writes config/reference.php in debug mode — a
     * PHP type-stub for IDE autocompletion of App::config([...]) callers.
     * Scafera uses YAML for configuration, so the stub has no audience; strip
     * the pass to prevent the file from being regenerated on cache warm.
     */
    private function removeSymfonyReferenceDumpPass(ContainerBuilder $container): void
    {
        if (!class_exists(PhpConfigReferenceDumpPass::class)) {
            return;
        }

        $passConfig = $container->getCompiler()->getPassConfig();
        $passConfig->setBeforeOptimizationPasses(array_values(array_filter(
            $passConfig->getBeforeOptimizationPasses(),
            static fn($pass) => !$pass instanceof PhpConfigReferenceDumpPass,
        )));
    }

    private function checkRequiredStructure(): void
    {
        if (\PHP_SAPI === 'cli') {
            return;
        }

        $violations = [];

        foreach (KernelStructureValidator::REQUIRED as $path => $type) {
            $full = $this->projectDir . '/' . $path;
            $missing = $type === 'file' ? !is_file($full) : !is_dir($full);

            if ($missing) {
                $kind = $type === 'file' ? 'file' : 'directory';
                $violations[] = '  - ' . $path . ': Required ' . $kind . ' does not exist.';
            }
        }

        if (!empty($violations)) {
            throw new \LogicException(
                "Scafera structure violation: required files or directories are missing.\n\n"
                . implode("\n", $violations)
                . "\n\nRun 'vendor/bin/scafera make:controller' to generate your first controller and set up the project structure.",
            );
        }
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

        $this->registerBuiltinServices($c);

        $this->loadArchitectureServices($c);

        $arch = $this->getArchitecturePackage();
        $archName = $arch ? ucfirst($arch->getName()) : '';
        $welcome = '<!DOCTYPE html><title>Scafera</title>'
            . '<body style="font-family:system-ui;text-align:center;padding-top:15%;">'
            . '<h1 style="margin-bottom:10px;color:#234da0">Scafera</h1>'
            . '<p style="color:#555;">Create a controller for the root page to get started:'
            . '<br><code>vendor/bin/scafera make:controller Index</code></p>'
            . ($archName ? '<p style="color:#999;font-size:0.85em;">' . $archName . ' Architecture</p>' : '')
            . '</body>';
        $c->parameters()->set('scafera.welcome', $welcome);

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

    private function registerBuiltinServices(ContainerConfigurator $c): void
    {
        $c->services()
            ->set(RequestResolver::class)
                ->autowire()
                ->tag('controller.argument_value_resolver', ['priority' => 10])
            ->set(ResponseListener::class)
                ->tag('kernel.event_subscriber')
            ->set(WelcomeListener::class)
                ->args([
                    '%scafera.welcome%',
                    $this->debug,
                ])
                ->tag('kernel.event_subscriber');

        $c->services()
            ->set(ValidateCommand::class)
                ->args([
                    $this->getProjectDir(),
                    tagged_iterator('scafera.validator'),
                    tagged_iterator('scafera.advisor'),
                ])
                ->tag('console.command')
            ->set(InfoPathsCommand::class)
                ->args([
                    $this->getProjectDir(),
                    tagged_iterator('scafera.path_provider'),
                ])
                ->tag('console.command');
    }

    private function registerGeneratorCommands(ContainerBuilder $container): void
    {
        $architecture = $this->getArchitecturePackage();
        if (!$architecture) {
            return;
        }

        $projectDir = $this->getProjectDir();

        foreach ($architecture->getGenerators() as $generator) {
            $definition = new Definition(MakeCommand::class);
            $definition->setArguments([$projectDir, $generator::class]);
            $definition->addTag('console.command', ['command' => 'make:' . $generator->getName()]);
            $container->setDefinition('scafera.make.' . $generator->getName(), $definition);
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
        $resourceDir = $projectDir . '/' . $discovery['resource'];

        if (!is_dir($resourceDir)) {
            return;
        }

        $services = $c->services()->defaults()->autowire()->autoconfigure();
        $services->load($discovery['namespace'], $resourceDir)
            ->exclude(array_map(fn($e) => $projectDir . '/' . $e, $discovery['exclude']));

        // Tag controller services so Symfony can resolve their dependencies.
        foreach ($architecture->getControllerPaths() as $path) {
            $controllerDir = $projectDir . '/' . $path;
            if (!is_dir($controllerDir)) {
                continue;
            }
            $ns = $this->pathToNamespace($discovery['namespace'], $discovery['resource'], $path);
            if ($ns !== null) {
                $c->services()->defaults()->autowire()->autoconfigure()
                    ->load($ns, $controllerDir)
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

        if (array_key_exists('parameters', $config)) {
            foreach ($config['parameters'] ?? [] as $key => $value) {
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
