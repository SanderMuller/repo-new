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

it('defaults laravelVersions for laravel-package', function (): void {
    $state = new WizardState();
    $state->category = 'laravel-package';
    $state->vendor = 'sandermuller';
    $state->applyDefaults();

    expect($state->laravelVersions)->toBe('^11.0||^12.0||^13.0')
        ->and($state->phpVersion)->toBe('8.3');
});

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
