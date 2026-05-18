<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\Wizard\Question;

use SanderMuller\RepoNew\Wizard\WizardState;
use Symfony\Component\Console\Style\SymfonyStyle;

final class PluginShapeQuestion
{
    private const array CHOICES = ['none', 'command-provider', 'event-subscriber', 'both'];

    public function ask(SymfonyStyle $io, WizardState $state): void
    {
        if ($state->category !== 'composer-plugin') {
            return;
        }

        if ($state->pluginShape !== null) {
            return;
        }

        $choice = $io->choice(
            'Composer plugin shape? Drives src/Plugin.php skeleton.',
            self::CHOICES,
            'none',
        );

        $state->pluginShape = (string) $choice;
    }
}
