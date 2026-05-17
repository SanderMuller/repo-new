<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\Wizard\Question;

use SanderMuller\RepoNew\Wizard\WizardState;
use Symfony\Component\Console\Style\SymfonyStyle;

final class PhpVersionQuestion
{
    public function ask(SymfonyStyle $io, WizardState $state): void
    {
        if ($state->phpVersion !== null) {
            return;
        }

        $state->phpVersion = $io->choice('PHP version?', ['8.3', '8.4', '8.5'], '8.3');
    }
}
