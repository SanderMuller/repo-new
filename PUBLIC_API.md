# Public API

`sandermuller/repo-new` is a CLI tool, not a library. The semver contract covers the **command-line interface only**.

## Versioning

This package follows [Semantic Versioning 2.0.0](https://semver.org/spec/v2.0.0.html). Pre-`1.0.0` releases may break API in MINOR bumps; we surface those in `CHANGELOG.md`.

## Stable surface (0.x)

The `repo` binary and its commands:

- `repo new [name]` — flags, arguments, exit codes, and the shape of `--help` output.
- The `repo` binary path itself (shipped via `composer.json` `bin`).

Run `repo new --help` for the current flag list. Flags will only be added or deprecated in MINOR releases; removed in MAJOR.

### Exit codes

| Code | Meaning |
|---|---|
| `0` | Success. |
| `64` | Usage error — missing required field in `--no-interaction` mode. |
| `65` | Runtime error during scaffold (surface message on stderr). |
| `70` | Autoloader not found — Composer install required. |

## Not public (everything else)

Everything under `SanderMuller\RepoNew\*` is **internal** and may change in any release, including patches. Do not import or extend these classes from downstream code:

- All classes in `src/` — `Application`, `NewCommand`, scaffolders, wizard, prompt builders, runners, locators, interfaces.
- All public methods and constants on those classes (they're `public` so Symfony Console can call them, not because they're part of the API).
- The structure of the handoff prompt printed at the end of scaffolding.

If you need a programmatic entry point, open an issue describing the use case before 1.0 ships.

## Removed APIs

<!-- Track removed APIs here when the public surface (CLI flags or binary name) changes. Example:
- `0.5.0` — Removed `--with-laravel-sets` flag. Use `--with-laravel-sets-v2` instead.
-->

None yet.
