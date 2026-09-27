<?php declare(strict_types=1);

use SanderMuller\RepoNew\HandoffPrompt\HandoffPromptBuilder;
use SanderMuller\RepoNew\Wizard\WizardState;

it('builds a non-empty handoff prompt for every wizard category', function (string $category): void {
    $state = new WizardState();
    $state->category = $category;
    $state->vendor = 'acme';
    $state->package = 'demo';

    // build() throws "No handoff template" if the category has no template —
    // this guards every category in NewCommand::CATEGORIES against that gap.
    $prompt = new HandoffPromptBuilder()->build($state, '/tmp/demo');

    expect($prompt)->not->toBeEmpty()
        ->and($prompt)->toContain('/tmp/demo');
})->with([
    'laravel-project',
    'laravel-package',
    'php-package',
    'phpstan-extension',
    'rector-extension',
    'composer-plugin',
    'skill-bundle',
]);

it('describes the laravel-project boost tool as laravel/boost, not package-boost', function (): void {
    $state = new WizardState();
    $state->category = 'laravel-project';

    $prompt = new HandoffPromptBuilder()->build($state, '/tmp/demo');

    expect($prompt)->toContain('laravel/boost')
        ->and($prompt)->not->toContain('package-boost')
        ->and($prompt)->not->toContain('Every step should be a no-op');
});

it('names the laravel-package variant the scaffold used', function (string $variant, string $expected): void {
    $state = new WizardState();
    $state->category = 'laravel-package';
    $state->variant = $variant;

    expect(new HandoffPromptBuilder()->build($state, '/tmp/demo'))->toContain($expected);
})->with([
    ['sander', 'plain Illuminate ServiceProvider'],
    ['spatie', 'spatie/laravel-package-tools based'],
]);
