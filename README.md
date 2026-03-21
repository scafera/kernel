# scafera/kernel

Symfony 8 boot layer for the Scafera framework. Treats Symfony as a dependency — user projects contain only business logic.

## What it provides

- `ScaferaKernel` — single boot class, user projects never define a Kernel
- `public/index.php` — HTTP entry point
- `bin/scafera` — CLI entry point (`vendor/bin/scafera`)
- `Bootstrap` — pre-boot environment preparation
- Dynamic bundle discovery from Composer's `installed.json`
- Architecture package support via `ArchitecturePackageInterface`

## Requirements

- PHP >= 8.4
- Symfony 8

## Usage

This package is the core boot layer for the Scafera framework. It is not intended to be used on its own — it serves as the foundation that other Scafera packages (architecture packages, bundles, meta-packages) build on.

## CLI

```bash
vendor/bin/scafera                    # Scafera commands
vendor/bin/scafera symfony            # All Symfony commands
```

## Testing

```bash
vendor/bin/phpunit -c tests/phpunit.xml
```

## License

MIT
