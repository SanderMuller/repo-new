<?php declare(strict_types=1);

use SanderMuller\RepoNew\Wizard\WizardState;

it('defaults to pest for sander vendor', function (): void {
    $state = new WizardState();
    $state->category = 'php-package';
    $state->vendor = 'sandermuller';
    $state->applyDefaults();

    expect($state->testFramework)->toBe('pest');
});

it('defaults to phpunit for hihaho vendor', function (): void {
    $state = new WizardState();
    $state->category = 'laravel-package';
    $state->vendor = 'hihaho';
    $state->applyDefaults();

    expect($state->testFramework)->toBe('phpunit');
});

it('always defaults to phpunit for phpstan-extension, regardless of vendor', function (): void {
    $state = new WizardState();
    $state->category = 'phpstan-extension';
    $state->vendor = 'sandermuller';
    $state->applyDefaults();

    expect($state->testFramework)->toBe('phpunit');
});

it('defaults to pest for composer-plugin under sandermuller (vendor-driven, same rule as other packages)', function (): void {
    $state = new WizardState();
    $state->category = 'composer-plugin';
    $state->vendor = 'sandermuller';
    $state->applyDefaults();

    expect($state->testFramework)->toBe('pest');
});

it('defaults to phpunit for composer-plugin under hihaho', function (): void {
    $state = new WizardState();
    $state->category = 'composer-plugin';
    $state->vendor = 'hihaho';
    $state->applyDefaults();

    expect($state->testFramework)->toBe('phpunit');
});

it('defaults laravelVersions for laravel-package', function (): void {
    $state = new WizardState();
    $state->category = 'laravel-package';
    $state->vendor = 'sandermuller';
    $state->applyDefaults();

    expect($state->laravelVersions)->toBe('^12.0||^13.0')
        ->and($state->phpVersion)->toBe('8.4');
});

it('defaults laravel-project to PHP 8.5, the newest stable', function (): void {
    $state = new WizardState();
    $state->category = 'laravel-project';
    $state->applyDefaults();

    expect($state->phpVersion)->toBe('8.5');
});

it('defaults the laravel-package variant by vendor', function (string $vendor, string $variant): void {
    $state = new WizardState();
    $state->category = 'laravel-package';
    $state->vendor = $vendor;
    $state->applyDefaults();

    expect($state->variant)->toBe($variant);
})->with([
    ['sandermuller', 'sander'],
    ['acme', 'sander'],
    ['hihaho', 'spatie'],
]);

it('turns hihaho rules on by default for a hihaho laravel-project only', function (string $vendor, bool $expected): void {
    $state = new WizardState();
    $state->category = 'laravel-project';
    $state->vendor = $vendor;
    $state->applyDefaults();

    expect($state->withHihahoRules)->toBe($expected);
})->with([
    ['hihaho', true],
    ['acme', false],
]);

it('keeps an explicit --no-with-hihaho-rules for vendor hihaho', function (): void {
    $state = new WizardState();
    $state->category = 'laravel-project';
    $state->vendor = 'hihaho';
    $state->withHihahoRules = false;
    $state->applyDefaults();

    expect($state->withHihahoRules)->toBeFalse();
});

it('defaults rector-extension to pest for sandermuller and phpunit for hihaho', function (string $vendor, string $framework): void {
    $state = new WizardState();
    $state->category = 'rector-extension';
    $state->vendor = $vendor;
    $state->applyDefaults();

    expect($state->testFramework)->toBe($framework);
})->with([
    ['sandermuller', 'pest'],
    ['hihaho', 'phpunit'],
]);

it('composerName returns vendor/package when both set', function (): void {
    $state = new WizardState();
    $state->vendor = 'foo';
    $state->package = 'bar';

    expect($state->composerName())->toBe('foo/bar');
});

it('composerName returns null when either half missing', function (): void {
    $state = new WizardState();
    $state->vendor = 'foo';

    expect($state->composerName())->toBeNull();
});
