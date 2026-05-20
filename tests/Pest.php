<?php declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a
| specific PHPUnit test case class. By default, that class is
| `PHPUnit\Framework\TestCase`. For Laravel-aware packages, the bootstrap
| phase replaces this with `Orchestra\Testbench\TestCase` for Feature
| tests, or a project-specific test case.
|
*/

// pest()->extend(Orchestra\Testbench\TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| Project-specific custom expectations go here. Pest's documentation has
| examples: https://pestphp.com/docs/custom-expectations
|
*/

// expect()->extend('toBeOne', fn () => $this->toBe(1));

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| Global helper functions used across multiple test files go here. Prefer
| Pest's higher-order test syntax over loose helpers where possible.
|
*/

/**
 * Absolute path to the repo-init installed as this project's Composer
 * dependency. Tests pin to this project-local copy (the version `composer.json`
 * resolved) rather than `RepoInitLocator`, whose production global-first lookup
 * would make tests depend on the dev machine's global Composer state.
 */
function repoInitPath(): string
{
    return dirname(__DIR__) . '/vendor/sandermuller/repo-init';
}
