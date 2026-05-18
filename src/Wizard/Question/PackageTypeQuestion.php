<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\Wizard\Question;

use SanderMuller\RepoNew\Wizard\WizardState;
use Symfony\Component\Console\Style\SymfonyStyle;

final class PackageTypeQuestion
{
    private const array CHOICES = [
        'laravel' => 'laravel-package',
        'php' => 'php-package',
        'phpstan' => 'phpstan-extension',
        'rector' => 'rector-extension',
        'composer-plugin' => 'composer-plugin',
    ];

    public function ask(SymfonyStyle $io, WizardState $state): void
    {
        if ($state->category !== null) {
            return;
        }

        $choice = $io->choice(
            'What kind of package?',
            array_keys(self::CHOICES),
            'laravel',
        );

        $state->category = self::CHOICES[$choice];
    }
}
