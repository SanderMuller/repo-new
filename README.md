# sandermuller/repo-new

[![Latest Version on Packagist](https://img.shields.io/packagist/v/sandermuller/repo-new.svg?style=flat-square)](https://packagist.org/packages/sandermuller/repo-new)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/sandermuller/repo-new/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/sandermuller/repo-new/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/sandermuller/repo-new.svg?style=flat-square)](https://packagist.org/packages/sandermuller/repo-new)
[![License](https://img.shields.io/packagist/l/sandermuller/repo-new.svg?style=flat-square)](LICENSE)

Interactive CLI wizard that scaffolds a new PHP repo against the canonical Sander / hihaho baseline defined by [`sandermuller/repo-init`](https://github.com/SanderMuller/repo-init). Same UX as `laravel new` — pick a category, answer a few questions, and get a repo with Composer dependencies installed, CI and quality tooling wired up, AI tooling synced, and `composer test` green on the first run.

## Install (one-time per machine)

```bash
composer global require sandermuller/repo-new
```

Make sure your global Composer `bin` directory (`~/.composer/vendor/bin` or `~/.config/composer/vendor/bin`) is on your `PATH`. `sandermuller/repo-init` — the source of the stubs and dependency lists — is pulled in automatically as a dependency; no separate install needed.

## Use

Interactive (recommended) — the wizard walks you through every choice:

```bash
repo new
```

Non-interactive (CI / scripting) — supply choices as flags:

```bash
repo new my-laravel-app \
  --type=laravel-project \
  --vendor=acme \
  --description="My new Laravel app" \
  --php=8.4 \
  --test-framework=phpunit
```

See `repo new --help` for the full flag list.

## Categories

repo-new scaffolds six repo categories. Choose one interactively, or pass `--type`:

| Category (`--type`) | What it scaffolds | Runtime dependencies wired in | Category options |
|---|---|---|---|
| `laravel-project` | A full Laravel application via `laravel new --boost`, with the shared tooling baseline overlaid on top | Laravel skeleton (`laravel new`) | `--with-hihaho-rules`, `--with-security-advisories` |
| `laravel-package` | A Laravel package — `spatie/laravel-package-tools`-based service provider, `src/`, `tests/`, publishable config | `illuminate/contracts`, `illuminate/support`, `spatie/laravel-package-tools` | `--laravel=<constraint>` |
| `php-package` | A framework-agnostic PHP library | none (pure library) | — |
| `phpstan-extension` | A PHPStan rule / extension package | `phpstan/phpstan: ^2` | `--laravel-aware` (swaps in `larastan/larastan`) |
| `rector-extension` | A Rector rule / ruleset package | `rector/rector: ^2`, `symplify/rule-doc-generator-contracts` | `--laravel-aware` (adds `driftingly/rector-laravel`) |
| `composer-plugin` | A Composer plugin — command provider and/or event subscriber skeleton | `composer-plugin-api: ^2.6` | `--plugin-shape=command-provider\|event-subscriber\|both\|none` |

## What it sets up

Beyond the category-specific source skeleton above, every scaffolded repo gets the same baseline:

- **Project files** — `composer.json` with PSR-4 autoloading, `src/` + `tests/`, plus `README.md`, `CHANGELOG.md`, `LICENSE`, `SECURITY.md`, `.editorconfig`, `.gitignore`, `.gitattributes` (lean published archive), and `.mcp.json`.
- **CI workflows** — GitHub Actions for the test suite, PHPStan, Pint, Rector, and changelog automation, plus a Dependabot config.
- **Quality tooling, configured and installed** — Pint (`pint.json`), PHPStan (`phpstan-baseline.neon`) with the strict / deprecation / PHPUnit / disallowed-calls / Symplify extension set, Rector with `type-perfect`, and `type-coverage` + `cognitive-complexity` analysis. All wired into `composer` scripts.
- **Test suite** — Pest or PHPUnit. PHPStan / Rector extensions and Laravel projects default to PHPUnit; other categories default to Pest (PHPUnit for the `hihaho` vendor). Override with `--test-framework`.
- **AI tooling** — `sandermuller/package-boost-php` + `boost-core` installed, then `vendor/bin/boost sync` run to generate `.ai/`, `.claude/`, `.agents/`, `.cursor/`, `AGENTS.md`, `CLAUDE.md`, and the per-agent skill directories.

Per-category runtime and dev dependencies come from `repo-init`'s `references/per-category-deps.yml`, so the dependency set always matches the current canonical baseline.

## How it works

1. The wizard collects: category → vendor → package name → description → PHP version → (Laravel constraint for `laravel-package`, plugin shape for `composer-plugin`) → test framework → opt-ins.
2. Runs `laravel new --boost` (for `laravel-project`) or copies the category stubs (for package categories) from the installed `repo-init`.
3. Substitutes placeholders (`__VENDOR__`, `__NAMESPACE__`, `__PACKAGE_STUDLY__`, …) across `composer.json`, source files, and CI workflows.
4. Pre-allows Composer plugins (`phpstan/extension-installer`, `pestphp/pest-plugin`) before requiring dependencies, so install never aborts on the plugin allowlist.
5. Runs `composer install` and the per-category `composer require` lists.
6. Fires `vendor/bin/boost sync` to generate the AI tooling files.
7. Initializes a git repo (add `--commit` for an initial commit) and prints a copy-pasteable handoff prompt for Claude or your agent of choice.

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
