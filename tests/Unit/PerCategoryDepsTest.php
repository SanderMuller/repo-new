<?php declare(strict_types=1);

use SanderMuller\RepoNew\RepoInit\PerCategoryDeps;
use SanderMuller\RepoNew\RepoInit\RepoInitLocator;

beforeEach(function (): void {
    $this->repoInit = (new RepoInitLocator())->locate();
    $this->deps = new PerCategoryDeps($this->repoInit . '/references/per-category-deps.yml');
});

it('returns expected dev deps for php-package with pest', function (): void {
    $list = $this->deps->forCategory('php-package', null, 'pest');

    $names = array_map(fn (string $e): string => trim(explode(':', $e, 2)[0]), $list->requireDev);

    expect($names)
        ->toContain('phpstan/phpstan', 'pestphp/pest', 'laravel/pint', 'stolt/lean-package-validator')
        ->and($names)->not->toContain('phpunit/phpunit');
});

it('drops phpstan/phpstan from shared dev deps for phpstan-extension', function (): void {
    $list = $this->deps->forCategory('phpstan-extension', null, 'phpunit');

    $devNames = array_map(fn (string $e): string => trim(explode(':', $e, 2)[0]), $list->requireDev);
    $reqNames = array_map(fn (string $e): string => trim(explode(':', $e, 2)[0]), $list->require);

    expect($devNames)->not->toContain('phpstan/phpstan')
        ->and($reqNames)->toContain('phpstan/phpstan');
});

it('drops rector/rector from shared dev deps for rector-extension', function (): void {
    $list = $this->deps->forCategory('rector-extension', null, 'pest');

    $devNames = array_map(fn (string $e): string => trim(explode(':', $e, 2)[0]), $list->requireDev);
    $reqNames = array_map(fn (string $e): string => trim(explode(':', $e, 2)[0]), $list->require);

    expect($devNames)->not->toContain('rector/rector')
        ->and($reqNames)->toContain('rector/rector');
});

it('replaces phpstan/phpstan with larastan when laravel-aware opt-in fires for phpstan-extension', function (): void {
    $list = $this->deps->forCategory('phpstan-extension', null, 'phpunit', ['laravel-aware' => true]);

    $reqNames = array_map(fn (string $e): string => trim(explode(':', $e, 2)[0]), $list->require);
    $devNames = array_map(fn (string $e): string => trim(explode(':', $e, 2)[0]), $list->requireDev);

    // illuminate/support is added to require.
    expect($reqNames)->toContain('illuminate/support')
        // larastan is added; phpstan/phpstan stays in require (it was added by mandatory).
        ->and($devNames)->toContain('larastan/larastan');
});

it('includes pest-plugin-laravel for laravel-package with pest', function (): void {
    $list = $this->deps->forCategory('laravel-package', 'sander', 'pest');

    $names = array_map(fn (string $e): string => trim(explode(':', $e, 2)[0]), $list->requireDev);

    expect($names)->toContain('pestphp/pest-plugin-laravel');
});

it('uses phpunit (no pest-plugin-laravel) for laravel-package with phpunit', function (): void {
    $list = $this->deps->forCategory('laravel-package', 'sander', 'phpunit');

    $names = array_map(fn (string $e): string => trim(explode(':', $e, 2)[0]), $list->requireDev);

    expect($names)->toContain('phpunit/phpunit')
        ->and($names)->not->toContain('pestphp/pest-plugin-laravel');
});

it('returns laravel-package-spatie stub variant for spatie', function (): void {
    expect($this->deps->stubVariantFor('laravel-package', 'spatie'))->toBe('laravel-package-spatie')
        ->and($this->deps->stubVariantFor('laravel-package', 'sander'))->toBe('laravel-package')
        ->and($this->deps->stubVariantFor('php-package', null))->toBe('php-package');
});
