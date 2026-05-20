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
    $prompt = (new HandoffPromptBuilder())->build($state, '/tmp/demo');

    expect($prompt)->toBeString()
        ->and($prompt)->not->toBeEmpty()
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
