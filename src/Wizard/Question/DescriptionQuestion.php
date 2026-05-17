<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\Wizard\Question;

use SanderMuller\RepoNew\Wizard\WizardState;
use Symfony\Component\Console\Style\SymfonyStyle;

final class DescriptionQuestion
{
    public function ask(SymfonyStyle $io, WizardState $state): void
    {
        if ($state->description !== null && $state->description !== '') {
            return;
        }

        $state->description = (string) $io->ask('One-line description?', 'A package that does X.');
    }
}
