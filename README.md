# sandermuller/repo-new

[![Latest Version on Packagist](https://img.shields.io/packagist/v/sandermuller/repo-new.svg?style=flat-square)](https://packagist.org/packages/sandermuller/repo-new)
[![Total Downloads](https://img.shields.io/packagist/dt/sandermuller/repo-new.svg?style=flat-square)](https://packagist.org/packages/sandermuller/repo-new)
[![License](https://img.shields.io/packagist/l/sandermuller/repo-new.svg?style=flat-square)](LICENSE)

Interactive CLI wizard that scaffolds a new PHP repo against the canonical Sander / hihaho baseline defined by [`sandermuller/repo-init`](https://github.com/SanderMuller/repo-init). Same UX as `laravel new` — pick a category, answer a few questions, get a wired-up repo with composer deps installed, AI tooling synced, and `composer test` green on first run.

## What it does

Walks you through 8 questions (category → vendor → package name → description → PHP version → Laravel constraint → test framework → opt-ins), then:

- Calls `laravel new --boost` for projects, or copies category-specific stubs for packages.
- Substitutes placeholders (`__VENDOR__`, `__NAMESPACE__`, `__PACKAGE_STUDLY__`, …) across composer.json, source files, CI workflows.
- Pre-allows composer plugins (`phpstan/extension-installer`, `pestphp/pest-plugin`) before requiring deps, so install never aborts on plugin allowlist errors.
- Runs `composer install` + per-category `composer require` lists from `repo-init`'s `references/per-category-deps.yml`.
- Fires `vendor/bin/testbench package-boost:sync` so `.ai/`, `.claude/`, `.agents/`, `.cursor/`, `AGENTS.md`, `CLAUDE.md`, etc. land alongside your code.
- Prints a copy-pasteable handoff prompt for Claude/your agent of choice.

Supports 5 categories: `laravel-project`, `laravel-package` (sander or spatie variant), `php-package`, `phpstan-extension`, `rector-extension`.

## Install (one-time per machine)

```bash
composer global require sandermuller/repo-new
```

Make sure `~/.composer/vendor/bin` (or equivalent) is on your `PATH`, then:

```bash
repo new my-new-package
```

## Use

Interactive (recommended):

```bash
repo new
```

Non-interactive (CI / scripting):

```bash
repo new my-laravel-app \
  --type=laravel-project \
  --vendor=acme \
  --description="My new Laravel app" \
  --php=8.4 \
  --test-framework=phpunit
```

See `repo new --help` for the full flag list.

## Testing

```bash
composer test
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md) and [releases](https://github.com/SanderMuller/repo-new/releases).

## Security

See [SECURITY.md](SECURITY.md).

## License

MIT — see [LICENSE](LICENSE).
