<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\Wizard\Question;

use SanderMuller\RepoNew\Wizard\PhpVersionPolicy;
use SanderMuller\RepoNew\Wizard\WizardState;
use Symfony\Component\Console\Style\SymfonyStyle;

final class PhpVersionQuestion
{
    public function ask(SymfonyStyle $io, WizardState $state): void
    {
        if ($state->phpVersion !== null) {
            return;
        }

        $allowed = PhpVersionPolicy::allowedFor($state->category);

        if (count($allowed) === 1) {
            $state->phpVersion = $allowed[0];

            return;
        }

        $chosen = $io->choice('PHP version?', $allowed, $allowed[0]);
        $state->phpVersion = is_string($chosen) ? $chosen : $allowed[0];
    }
}
