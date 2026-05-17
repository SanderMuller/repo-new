<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\Wizard\Question;

use InvalidArgumentException;
use SanderMuller\RepoNew\Wizard\WizardState;
use Symfony\Component\Console\Style\SymfonyStyle;

final class NameQuestion
{
    public function ask(SymfonyStyle $io, WizardState $state): void
    {
        if ($state->vendor !== null && $state->package !== null) {
            return;
        }

        $default = $state->composerName() ?? 'sandermuller/my-new-package';

        $answer = (string) $io->ask(
            'Composer name? Format: <vendor>/<name> (kebab-case)',
            $default,
            self::validate(...),
        );

        [$vendor, $package] = explode('/', $answer, 2);
        $state->vendor = $vendor;
        $state->package = $package;
    }

    public static function validate(mixed $answer): string
    {
        if (! is_string($answer) || preg_match('#^[a-z0-9]([a-z0-9._-]*[a-z0-9])?/[a-z0-9]([a-z0-9._-]*[a-z0-9])?$#', $answer) !== 1) {
            throw new InvalidArgumentException('Composer name must be <vendor>/<name>, lowercase kebab-case.');
        }

        return $answer;
    }
}
