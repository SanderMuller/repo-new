<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\Wizard\Question;

use SanderMuller\RepoNew\Wizard\WizardState;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ProjectVsPackageQuestion
{
    public function ask(SymfonyStyle $io, WizardState $state): void
    {
        if ($state->category !== null) {
            return;
        }

        $choice = $io->choice(
            'Is this a project (full Laravel app) or a package (library)?',
            ['project', 'package'],
            'package',
        );

        if ($choice === 'project') {
            $state->category = 'laravel-project';
        }
        // If 'package': category is set by PackageTypeQuestion.
    }
}
