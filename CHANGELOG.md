# Changelog

All notable changes to `sandermuller/repo-new` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Pre-`1.0.0` releases may introduce breaking changes in MINOR bumps; we surface those here clearly.

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
