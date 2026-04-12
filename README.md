# scafera/kernel

Scafera Kernel is the execution core of the Scafera framework. It provides a minimal, controlled runtime environment and defines the boundaries within which applications operate.

It treats Symfony as a dependency — user projects contain only business logic.

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

## CLI

```bash
vendor/bin/scafera                    # Scafera commands
vendor/bin/scafera validate           # Validate project against architecture rules
vendor/bin/scafera about              # Show framework info
vendor/bin/scafera symfony            # All Symfony commands
```

## Requirements

- PHP >= 8.4
- Symfony 8

## Usage

This package is not intended to be used directly. It is installed as part of a Scafera-based setup and works in combination with an architecture package that defines the application structure.

## License

MIT
