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
| `GeneratorInterface` | Code generator for `scafera make` command |
| `ViewInterface` | Template rendering (implemented by `scafera/frontend`) |

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
