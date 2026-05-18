<?php declare(strict_types=1);

use SanderMuller\RepoNew\Wizard\Question\PluginShapeQuestion;
use SanderMuller\RepoNew\Wizard\WizardState;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

function makeIo(): SymfonyStyle
{
    return new SymfonyStyle(new ArrayInput([]), new BufferedOutput());
}

it('skips when category is not composer-plugin', function (): void {
    $state = new WizardState();
    $state->category = 'php-package';

    (new PluginShapeQuestion())->ask(makeIo(), $state);

    expect($state->pluginShape)->toBeNull();
});

it('skips when pluginShape already set (e.g. via --plugin-shape flag)', function (): void {
    $state = new WizardState();
    $state->category = 'composer-plugin';
    $state->pluginShape = 'both';

    (new PluginShapeQuestion())->ask(makeIo(), $state);

    expect($state->pluginShape)->toBe('both');
});
