# Changelog

All notable changes to `sandermuller/repo-new` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Pre-`1.0.0` releases may introduce breaking changes in MINOR bumps; we surface those here clearly.

## 0.5.0 - 2026-05-20

### Added

- **New `skill-bundle` category.** A seventh scaffold category for boost-core skill bundles — packages that ship pure-markdown AI skills under `resources/boost/skills/` with no PHP source and no test runner. Selectable interactively or via `--type=skill-bundle`. It scaffolds a lean repo: `sandermuller/boost-core` as the runtime dependency, Pint + `lean-package-validator` for tooling, and skips the PHP toolchain (PHPStan, Rector, PHPUnit, `.mcp.json`, the `phpstan` / `rector-check` workflows) that the code-bearing categories get.
- **`consumes-shared-dev-deps` support.** `PerCategoryDeps` now honors the `consumes-shared-dev-deps` key in `repo-init`'s `per-category-deps.yml` — when a category sets it to `false`, its dependency set comes solely from its own `mandatory` block, skipping the shared dependency lists.

### Changed

- **Bumped `sandermuller/repo-init` to `^0.5.0`.** repo-new 0.5.0 requires repo-init 0.5.0+, which ships the `skill-bundle` stubs and `per-category-deps.yml` entry. The existing six categories also pick up repo-init 0.5.0's corrected per-category boost-family packages and `post-install-cmd` / `post-update-cmd` scripts.
- **`laravel-package` always scaffolds the `spatie/laravel-package-tools` shape.** The generated service provider extends `Spatie\LaravelPackageTools\PackageServiceProvider` with a declarative `configurePackage()`.
- **`RepoInitLocator` resolves repo-new's own pinned `repo-init` first.** It previously preferred an ambient global install, which could resolve a `repo-init` version different from the one repo-new's `composer.json` pins. The lookup now tries repo-new's own resolved dependency (deterministic, install-relative) before any global or ambient copy.

### Removed

- **BREAKING — the `--variant` flag is removed.** `laravel-package` no longer offers a "Sander-style or Spatie-style?" choice — it is always `spatie/laravel-package-tools`-based. `--variant=sander` and `--variant=spatie` now error.

### Upgrade notes

If you scripted `repo new --type=laravel-package --variant=...`, drop the `--variant` flag — `laravel-package` is always `spatie/laravel-package-tools`-based now. Every other category is unaffected. `composer global update sandermuller/repo-new` pulls repo-init 0.5.0 automatically.

**Full Changelog**: https://github.com/SanderMuller/repo-new/compare/0.4.0...0.5.0

## 0.4.0 - 2026-05-20

### Changed

- **Bumped `sandermuller/repo-init` floor from `^0.3.0` to `^0.4.0`.** Aligns repo-new with the boost-core 0.4.0 family release — `repo-init 0.4.0`, plus `package-boost-php 0.4.0` and `boost-core 0.4.0` pulled transitively. The wizard's behaviour is unchanged: the `references/per-category-deps.yml` schema and the `stubs/` layout repo-new consumes are identical in repo-init 0.4.0.

### Upgrade notes

`composer global update sandermuller/repo-new` picks up `repo-init 0.4.0` and `boost-core 0.4.0` automatically.

repo-init 0.4.0 changes where globally-installed packages place their AI skill files under your home directory: `~/.{agent}/skills/repo-init/` becomes `~/.{agent}/skills/sandermuller__repo-init/`, namespaced by the full `vendor__package` slug. boost-core's migrator performs a one-time, ownership-checked rename of the legacy directory on the first global update — no manual action needed. Project-scope `.claude/skills/` directories are unaffected.

repo-new itself is unaffected by this path change: it locates the installed `repo-init` through Composer's vendor directories, never the agent skill directory.

**Full Changelog**: https://github.com/SanderMuller/repo-new/compare/0.3.0...0.4.0

## 0.3.0 - 2026-05-18

### Added

- **New `composer-plugin` category.** Sixth wizard category for framework-agnostic Composer plugins (event subscribers, command providers, etc.). Selectable via the interactive prompt or `--type=composer-plugin`.
- **`--plugin-shape` option** with matching interactive prompt. Values: `command-provider | event-subscriber | both | none` (default `none`). Drives the `src/Plugin.php` skeleton selection from `sandermuller/repo-init`'s `stubs/composer-plugin/src/Plugin.{shape}.php` variants and decides whether `src/CommandProvider.php` ships in the scaffold.
- **Vendor-driven `composer-plugin` test framework default** — Pest for `sandermuller/*`, PHPUnit for `hihaho/*`, matches existing per-vendor convention. `--test-framework` overrides as before.
- **Handoff template** for `composer-plugin` scaffolds with new `{PLUGIN_SHAPE}` placeholder.

### Changed

- **Bumped `sandermuller/repo-init` floor to `^0.3.0`** so the wizard reads the new `composer-plugin` section from `references/per-category-deps.yml` and resolves the new stub variants.
- **Scaffold-time AI sync command swapped** from `vendor/bin/testbench package-boost:sync` to `vendor/bin/boost sync` in both `LaravelProjectScaffolder` and `PackageScaffolder`. The standalone `boost` binary is shipped by `sandermuller/boost-core` (pulled transitively via `sandermuller/package-boost-php`) and works in both Laravel apps and package dev installs. The old testbench-routed path was a silent no-op for `laravel-project` scaffolds (testbench is package-dev only).

### Removed

- **Dropped `sandermuller/package-boost`** from `require-dev`. Superseded by `sandermuller/package-boost-php` + `sandermuller/boost-core` (the new framework-agnostic split), pulled transitively via `repo-init`.

### Internal

- `boost.php` shipped at repo root (boost-core convention; agents enabled: claude-code, copilot, codex).
- `composer.json` `config.allow-plugins` extended for `sandermuller/boost-core` + `sandermuller/package-boost-php`.
- `AGENTS.md`, `CLAUDE.md`, `.agents/skills/`, `.claude/skills/`, `.github/skills/` untracked — boost-core's managed `.gitignore` block now owns regeneration via `vendor/bin/boost sync`.

### Upgrade notes

Consumers installing via `composer global require sandermuller/repo-new` will pick up the new `repo-init 0.3.0` baseline and `boost-core 0.3.x` automatically. On first install you will be prompted to allow the two new composer plugins; accept both.

**Full Changelog**: https://github.com/SanderMuller/repo-new/compare/0.2.0...0.3.0

## 0.2.0 - 2026-05-17

**Full Changelog**: https://github.com/SanderMuller/repo-new/compare/0.1.0...0.2.0

## 0.1.0 - 2026-05-17

**Full Changelog**: https://github.com/SanderMuller/repo-new/commits/0.1.0

## [Unreleased]

### Added

- New sixth category `composer-plugin` (Composer plugins / boost-style tooling). Detection via `composer.json` `type: composer-plugin` in the wizard; available via interactive prompt and `--type=composer-plugin`.
- New `--plugin-shape` CLI option (and matching interactive prompt) for `composer-plugin`. Values: `command-provider|event-subscriber|both|none`. Default `none`. Drives `src/Plugin.php` skeleton selection from repo-init's `stubs/composer-plugin/src/Plugin.{shape}.php` variants and decides whether `src/CommandProvider.php` ships.
- `composer-plugin` default test framework follows the vendor-driven rule (sandermuller → pest, hihaho → phpunit; override via `--test-framework`).
- Handoff template `composer-plugin.txt` with new `{PLUGIN_SHAPE}` placeholder.
