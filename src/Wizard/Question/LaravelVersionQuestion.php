<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\Wizard\Question;

use SanderMuller\RepoNew\Wizard\WizardState;
use Symfony\Component\Console\Style\SymfonyStyle;

final class LaravelVersionQuestion
{
    public function ask(SymfonyStyle $io, WizardState $state): void
    {
        if ($state->category !== 'laravel-package') {
            return;
        }

        if ($state->laravelVersions !== null) {
            return;
        }

        $state->laravelVersions = $io->choice(
            'Laravel version range?',
            ['^11.0||^12.0||^13.0', '^12.0||^13.0', '^13.0'],
            '^11.0||^12.0||^13.0',
        );
    }
}
