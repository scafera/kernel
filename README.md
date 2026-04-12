# scafera/kernel

Scafera Kernel is the execution core of the Scafera framework. It provides a minimal, controlled runtime environment and defines the boundaries within which applications operate.

It treats Symfony as a dependency — user projects contain only business logic.

Scafera is opinionated by design — it enforces boundaries, limits configuration surface, and removes decisions that don't belong to application developers. The goal is a framework that is predictable and hard to misuse.

## Headless by design

The kernel is intentionally non-functional without an architecture package. Without one:

- No user services are discovered or registered
- No routes are loaded (HTTP returns 404)
- Only built-in commands work (`about`, `validate`, `cache:clear`)

Install an architecture package (e.g. `scafera/layered`) to define structure, behavior, and rules.

## What it provides

- `ScaferaKernel` — single boot class, user projects never define a Kernel
- `public/index.php` — HTTP entry point
- `bin/scafera` — CLI entry point (`vendor/bin/scafera`)
- `Bootstrap` — pre-boot environment preparation
- Architecture package support via `ArchitecturePackageInterface`

## Design principles

- **Explicit execution** — no hidden or implicit behavior in userland
- **Separation of concerns** — runtime and architecture are independent
- **Extensibility through contracts** — behavior is defined by implementing packages

## How it works

### Dynamic bundle discovery

Bundles are discovered automatically from Composer's `installed.json`. Any installed package declaring `"type": "symfony-bundle"` is registered at boot — no `config/bundles.php` needed.

- Install a capability package and its bundle is available
- Remove a package and its bundle disappears
- `composer install --no-dev` naturally excludes dev bundles

### Configuration

User configuration goes in a single optional file:

```
config/config.yaml
```

This file can override any bundle configuration and set environment variables:

```yaml
env:
  APP_SECRET: your-secret-here

framework:
  session:
    cookie_secure: true
```

There is no `config/packages/` directory — the kernel does not scan for it.

### Environment bootstrap

The `Bootstrap` class handles environment setup before the Symfony runtime takes over:

1. Sets `APP_ENV` and `APP_DEBUG` defaults (`dev` / `1`)
2. Reads the `env:` section from `config/config.yaml` if present
3. Real OS environment variables always take precedence
4. Validates that `APP_SECRET` is set

## Contracts

The kernel defines contracts that architecture and capability packages implement:

| Contract | Purpose |
|----------|---------|
| `ArchitecturePackageInterface` | Defines an architecture package |
| `ValidatorInterface` | Hard validation rule (affects exit code) |
| `AdvisorInterface` | Soft advisory check (never affects exit code) |
| `GeneratorInterface` | Code generator for `scafera make:*` commands |
| `ViewInterface` | Template rendering (implemented by `scafera/frontend`) |

## HTTP types

Controllers use these types instead of Symfony's HTTP classes directly. The `ControllerBoundaryPass` enforces this at compile time.

| Type | Purpose |
|------|---------|
| `Scafera\Kernel\Http\Request` | Wraps the incoming HTTP request |
| `Scafera\Kernel\Http\Response` | Plain HTTP response |
| `Scafera\Kernel\Http\JsonResponse` | JSON HTTP response |
| `Scafera\Kernel\Http\RedirectResponse` | Redirect HTTP response |
| `Scafera\Kernel\Http\Route` | Attribute for defining routes |
| `Scafera\Kernel\Http\ParameterBag` | Query and request parameters (typed getters) |
| `Scafera\Kernel\Http\HeaderBag` | Request headers (case-insensitive) |

All types live in `Scafera\Kernel\Http\`.

### Console

| Type | Purpose |
|------|---------|
| `Scafera\Kernel\Console\Command` | Base command class with `handle()` method |
| `Scafera\Kernel\Console\Input` | Command input wrapper |
| `Scafera\Kernel\Console\Output` | Command output wrapper with `success()`, `error()`, `warning()` |
| `Scafera\Kernel\Console\Attribute\AsCommand` | `#[AsCommand]` attribute |

### Testing

| Type | Purpose |
|------|---------|
| `Scafera\Kernel\Test\WebTestCase` | HTTP test base with `get()`, `post()`, etc. |
| `Scafera\Kernel\Test\TestResponse` | Fluent assertions: `assertOk()`, `assertJsonPath()`, etc. |
| `Scafera\Kernel\Test\CommandTestCase` | Console test base |
| `Scafera\Kernel\Test\CommandResult` | Command output assertions |

### Example controller

```php
use Scafera\Kernel\Http\Request;
use Scafera\Kernel\Http\Response;
use Scafera\Kernel\Http\Route;

#[Route('/orders/{id}', methods: 'GET', requirements: ['id' => '\d+'])]
final class ShowOrder
{
    public function __invoke(Request $request): Response
    {
        $id = $request->routeParam('id');
        // ...
        return new Response($content);
    }
}
```

### Request

Properties:
- `$request->query` — `ParameterBag` of query string parameters
- `$request->request` — `ParameterBag` of POST body parameters
- `$request->headers` — `HeaderBag` of request headers

Methods:
- `getMethod(): string` — HTTP method (GET, POST, etc.)
- `isMethod(string $method): bool`
- `getPath(): string` — URL path without query string
- `getUri(): string` — full URI
- `getContent(): string` — raw request body
- `json(): array` — parsed JSON body
- `routeParam(string $key, mixed $default = null): mixed` — single route parameter
- `routeParams(): array` — all route parameters

### Response types

```php
new Response(string $content = '', int $status = 200, array $headers = [])
new JsonResponse(mixed $data = null, int $status = 200, array $headers = [])
new RedirectResponse(string $url, int $status = 302, array $headers = [])
```

### Route attribute

```php
#[Route(
    path: '/path/{param}',
    methods: 'GET',              // string or array: ['GET', 'POST']
    name: 'custom_route_name',   // optional, auto-generated if omitted
    requirements: ['param' => '\d+'],
    defaults: ['param' => '1'],
)]
```

Class-level `#[Route]` sets a prefix for method-level routes. A class-level `#[Route]` with no method-level routes maps to `__invoke`.

### ParameterBag

- `get(string $key, mixed $default = null): mixed`
- `has(string $key): bool`
- `all(): array`
- `getInt(string $key, int $default = 0): int`
- `getString(string $key, string $default = ''): string`
- `getBoolean(string $key, bool $default = false): bool`

### HeaderBag

- `get(string $key, ?string $default = null): ?string` — case-insensitive lookup
- `has(string $key): bool`
- `all(): array`

## Built-in commands

```bash
vendor/bin/scafera validate           # Run validators from the installed architecture package
vendor/bin/scafera about              # Show framework and environment information
vendor/bin/scafera symfony            # All Symfony commands
```

## What the kernel does NOT own

- **Folder conventions** — defined by architecture packages
- **Twig / presentation** — `scafera/frontend`
- **Generators and validators** — architecture packages + `scafera/tools`
- **Profiler / debug toolbar** — `scafera/profiler`
- **Business logic** — your project

## Requirements

- PHP >= 8.4
- Symfony 8

## Usage

This package is not intended to be used directly. It is installed as part of a Scafera-based setup and works in combination with an architecture package that defines the application structure.

## License

MIT
