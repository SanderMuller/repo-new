<?php declare(strict_types=1);

use SanderMuller\RepoNew\Wizard\Question\SkillTagsQuestion;
use SanderMuller\RepoNew\Wizard\WizardState;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

function skillTagsIo(): SymfonyStyle
{
    return new SymfonyStyle(new ArrayInput([]), new BufferedOutput());
}

it('skips laravel-project — it has no .config/boost.php to fill', function (): void {
    $state = new WizardState();
    $state->category = 'laravel-project';

    (new SkillTagsQuestion())->ask(skillTagsIo(), $state);

    expect($state->skillTags)->toBeNull();
});

it('skips when skillTags already set (e.g. via --skill-tags flag)', function (): void {
    $state = new WizardState();
    $state->category = 'php-package';
    $state->skillTags = ['php'];

    (new SkillTagsQuestion())->ask(skillTagsIo(), $state);

    expect($state->skillTags)->toBe(['php']);
});

it('defaults to the php tag in non-interactive mode for package categories', function (): void {
    $state = new WizardState();
    $state->category = 'php-package';
    $state->interactive = false;

    (new SkillTagsQuestion())->ask(skillTagsIo(), $state);

    expect($state->skillTags)->toBe(['php']);
});
