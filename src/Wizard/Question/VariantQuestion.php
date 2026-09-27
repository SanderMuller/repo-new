<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\Wizard\Question;

use SanderMuller\RepoNew\Wizard\WizardState;
use Symfony\Component\Console\Style\SymfonyStyle;

final class VariantQuestion
{
    public const array VARIANTS = ['sander', 'spatie'];

    public function ask(SymfonyStyle $io, WizardState $state): void
    {
        if ($state->category !== 'laravel-package' || $state->variant !== null || ! $state->interactive) {
            return;
        }

        $chosen = $io->choice(
            'Service provider base? (sander = plain ServiceProvider, spatie = spatie/laravel-package-tools)',
            self::VARIANTS,
            $state->vendor === 'hihaho' ? 'spatie' : 'sander',
        );
        $state->variant = is_string($chosen) ? $chosen : null;
    }
}
