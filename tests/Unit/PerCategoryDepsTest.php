<?php declare(strict_types=1);

use SanderMuller\RepoNew\RepoInit\PerCategoryDeps;

beforeEach(function (): void {
    $this->repoInit = repoInitPath();
    $this->deps = new PerCategoryDeps($this->repoInit . '/references/per-category-deps.yml');
});

it('returns expected dev deps for php-package with pest', function (): void {
    $list = $this->deps->forCategory('php-package', 'pest');

    $names = array_map(fn (string $e): string => trim(explode(':', $e, 2)[0]), $list->requireDev);

    expect($names)
        ->toContain('phpstan/phpstan', 'pestphp/pest', 'laravel/pint', 'stolt/lean-package-validator')
        ->and($names)->not->toContain('phpunit/phpunit');
});

it('drops phpstan/phpstan from shared dev deps for phpstan-extension', function (): void {
    $list = $this->deps->forCategory('phpstan-extension', 'phpunit');

    $devNames = array_map(fn (string $e): string => trim(explode(':', $e, 2)[0]), $list->requireDev);
    $reqNames = array_map(fn (string $e): string => trim(explode(':', $e, 2)[0]), $list->require);

    expect($devNames)->not->toContain('phpstan/phpstan')
        ->and($reqNames)->toContain('phpstan/phpstan');
});

it('drops rector/rector from shared dev deps for rector-extension', function (): void {
    $list = $this->deps->forCategory('rector-extension', 'pest');

    $devNames = array_map(fn (string $e): string => trim(explode(':', $e, 2)[0]), $list->requireDev);
    $reqNames = array_map(fn (string $e): string => trim(explode(':', $e, 2)[0]), $list->require);

    expect($devNames)->not->toContain('rector/rector')
        ->and($reqNames)->toContain('rector/rector');
});

it('replaces phpstan/phpstan with larastan when laravel-aware opt-in fires for phpstan-extension', function (): void {
    $list = $this->deps->forCategory('phpstan-extension', 'phpunit', ['laravel-aware' => true]);

    $reqNames = array_map(fn (string $e): string => trim(explode(':', $e, 2)[0]), $list->require);
    $devNames = array_map(fn (string $e): string => trim(explode(':', $e, 2)[0]), $list->requireDev);

    // illuminate/support is added to require.
    expect($reqNames)->toContain('illuminate/support')
        // larastan is added; phpstan/phpstan stays in require (it was added by mandatory).
        ->and($devNames)->toContain('larastan/larastan');
});

it('includes pest-plugin-laravel for laravel-package with pest', function (): void {
    $list = $this->deps->forCategory('laravel-package', 'pest');

    $names = array_map(fn (string $e): string => trim(explode(':', $e, 2)[0]), $list->requireDev);

    expect($names)->toContain('pestphp/pest-plugin-laravel');
});

it('uses phpunit (no pest-plugin-laravel) for laravel-package with phpunit', function (): void {
    $list = $this->deps->forCategory('laravel-package', 'phpunit');

    $names = array_map(fn (string $e): string => trim(explode(':', $e, 2)[0]), $list->requireDev);

    expect($names)->toContain('phpunit/phpunit')
        ->and($names)->not->toContain('pestphp/pest-plugin-laravel');
});

it('maps laravel-package to the spatie stub directory', function (): void {
    expect($this->deps->stubDirFor('laravel-package'))->toBe('laravel-package-spatie')
        ->and($this->deps->stubDirFor('php-package'))->toBe('php-package')
        ->and($this->deps->stubDirFor('composer-plugin'))->toBe('composer-plugin')
        ->and($this->deps->stubDirFor('skill-bundle'))->toBe('skill-bundle');
});

it('takes skill-bundle deps solely from its mandatory block (consumes-shared-dev-deps false)', function (): void {
    $list = $this->deps->forCategory('skill-bundle', 'pest');

    $reqNames = array_map(fn (string $e): string => trim(explode(':', $e, 2)[0]), $list->require);
    $devNames = array_map(fn (string $e): string => trim(explode(':', $e, 2)[0]), $list->requireDev);

    // consumes-shared-dev-deps: false → require-dev is the category's own
    // mandatory block, with NO shared.always / shared.test-framework merged in.
    expect($reqNames)->toContain('sandermuller/boost-core')
        ->and($devNames)->toContain('laravel/pint', 'stolt/lean-package-validator')
        ->and($devNames)->not->toContain('rector/rector', 'pestphp/pest', 'orchestra/testbench', 'mrpunyapal/rector-pest');
});

it('returns the shared-stub-skip denylist per category', function (): void {
    expect($this->deps->sharedStubSkipFor('laravel-project'))
        ->toContain('.config/boost.php', '_gitattributes', 'tests/')
        ->and($this->deps->sharedStubSkipFor('skill-bundle'))
        ->toContain('.mcp.json', 'phpstan-baseline.neon', 'tests/')
        // php-package has no shared-stub-skip key — copies all of stubs/shared/.
        ->and($this->deps->sharedStubSkipFor('php-package'))
        ->toBeEmpty();
});
