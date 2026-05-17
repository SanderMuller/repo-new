<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\Wizard\Question;

use SanderMuller\RepoNew\Wizard\WizardState;
use Symfony\Component\Console\Style\SymfonyStyle;

final class VariantQuestion
{
    public function ask(SymfonyStyle $io, WizardState $state): void
    {
        if ($state->category !== 'laravel-package') {
            return;
        }

        if ($state->variant !== null) {
            return;
        }

        $choice = $io->choice(
            'Sander-style or Spatie-style?',
            ['sander', 'spatie'],
            'sander',
        );

        $state->variant = $choice;
    }
}
