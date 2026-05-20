<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\Wizard;

use SanderMuller\RepoNew\Wizard\Question\DescriptionQuestion;
use SanderMuller\RepoNew\Wizard\Question\LaravelAwareQuestion;
use SanderMuller\RepoNew\Wizard\Question\LaravelVersionQuestion;
use SanderMuller\RepoNew\Wizard\Question\NameQuestion;
use SanderMuller\RepoNew\Wizard\Question\PackageTypeQuestion;
use SanderMuller\RepoNew\Wizard\Question\PhpVersionQuestion;
use SanderMuller\RepoNew\Wizard\Question\PluginShapeQuestion;
use SanderMuller\RepoNew\Wizard\Question\ProjectVsPackageQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Drives the wizard. Each question is a no-op if its target state field
 * is already populated (from --flag overrides). This makes --no-interaction
 * mode work uniformly: pre-populate via flags, then call run() and the
 * wizard skips every question that's already answered.
 */
final class Wizard
{
    public function run(SymfonyStyle $io, WizardState $state): void
    {
        (new ProjectVsPackageQuestion())->ask($io, $state);
        (new PackageTypeQuestion())->ask($io, $state);
        (new NameQuestion())->ask($io, $state);
        (new DescriptionQuestion())->ask($io, $state);
        (new PhpVersionQuestion())->ask($io, $state);
        (new LaravelVersionQuestion())->ask($io, $state);
        (new LaravelAwareQuestion())->ask($io, $state);
        (new PluginShapeQuestion())->ask($io, $state);

        $state->applyDefaults();
    }
}
