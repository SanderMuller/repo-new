<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\Wizard\Question;

use SanderMuller\RepoNew\Wizard\WizardState;
use Symfony\Component\Console\Style\SymfonyStyle;

final class LaravelAwareQuestion
{
    public function ask(SymfonyStyle $io, WizardState $state): void
    {
        if (! in_array($state->category, ['phpstan-extension', 'rector-extension'], true)) {
            return;
        }

        // Already set via flag.
        if ($state->laravelAware) {
            return;
        }

        $state->laravelAware = $io->confirm('Laravel-aware extension? (adds illuminate/support + larastan)', false);
    }
}
