<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\Scaffolder;

use RuntimeException;
use SanderMuller\RepoNew\Wizard\WizardState;

/**
 * Dispatches per category.
 */
final readonly class Scaffolder
{
    public function __construct(
        private PackageScaffolder $packageScaffolder,
        private LaravelProjectScaffolder $projectScaffolder,
    ) {}

    /**
     * @return array<string, int|string>
     */
    public function scaffold(WizardState $state, string $targetDir): array
    {
        return match ($state->category) {
            'laravel-project' => $this->projectScaffolder->scaffold($state, $targetDir),
            'laravel-package', 'php-package', 'phpstan-extension', 'rector-extension' => $this->packageScaffolder->scaffold($state, $targetDir),
            default => throw new RuntimeException("Unknown category: {$state->category}"),
        };
    }
}
