<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\Scaffolder;

use SanderMuller\RepoNew\Composer\ComposerRunnerInterface;
use SanderMuller\RepoNew\RepoInit\PerCategoryDeps;
use SanderMuller\RepoNew\RepoInit\PlaceholderSubstituter;
use SanderMuller\RepoNew\RepoInit\StubReader;
use SanderMuller\RepoNew\Wizard\WizardState;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Scaffolds php-package, laravel-package, phpstan-extension, rector-extension.
 *
 * Steps (per spec §5 package categories):
 *  1. Copy stubs/shared/* (substituted).
 *  2. Copy stubs/<category-or-variant>/* (substituted).
 *  3. composer install.
 *  4. composer require --dev <list>.
 *  5. composer require <runtime list>.
 *
 * Laravel-aware opt-in for extension categories is honored BEFORE composer
 * install via PerCategoryDeps.forCategory().
 */
final class PackageScaffolder
{
    public function __construct(
        private readonly SymfonyStyle $io,
        private readonly StubReader $stubReader,
        private readonly PerCategoryDeps $deps,
        private readonly ComposerRunnerInterface $composer,
    ) {}

    /**
     * @return array{stubsWritten: int, requireInstalled: int, requireDevInstalled: int}
     */
    public function scaffold(WizardState $state, string $targetDir): array
    {
        $substituter = new PlaceholderSubstituter($state);

        $written = 0;
        $written += $this->copyStubs('shared', $targetDir, $substituter);

        $stubVariant = $this->deps->stubVariantFor($state->category ?? '', $state->variant);
        $written += $this->copyStubs($stubVariant, $targetDir, $substituter);

        $optInFlags = $this->optInFlagsFromState($state);
        $depList = $this->deps->forCategory($state->category ?? '', $state->variant, $state->testFramework ?? 'pest', $optInFlags);

        // Substitute placeholders in dep constraints (e.g. illuminate/support: __LARAVEL_VERSIONS__).
        $require = array_map(static fn (string $e): string => $substituter->substitute($e), $depList->require);
        $requireDev = array_map(static fn (string $e): string => $substituter->substitute($e), $depList->requireDev);

        $this->composer->install($targetDir);

        if ($require !== []) {
            $this->composer->require($targetDir, $require, dev: false);
        }
        if ($requireDev !== []) {
            $this->composer->require($targetDir, $requireDev, dev: true);
        }

        return [
            'stubsWritten' => $written,
            'requireInstalled' => count($require),
            'requireDevInstalled' => count($requireDev),
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function optInFlagsFromState(WizardState $state): array
    {
        return match ($state->category) {
            'laravel-project' => [
                'with-hihaho-rules' => $state->withHihahoRules,
                'with-security-advisories' => $state->withSecurityAdvisories,
            ],
            'laravel-package' => [
                'hihaho-package-tools-flavoured' => $state->variant === 'spatie',
            ],
            'phpstan-extension', 'rector-extension' => [
                'laravel-aware' => $state->laravelAware,
            ],
            default => [],
        };
    }

    private function copyStubs(string $stubDir, string $targetDir, PlaceholderSubstituter $substituter): int
    {
        $count = 0;

        foreach ($this->stubReader->read($stubDir) as $stub) {
            $relativeSubstituted = $substituter->substitute($stub['relative']);
            $destination = $targetDir . '/' . $relativeSubstituted;

            $destinationDir = dirname($destination);
            if (! is_dir($destinationDir) && ! mkdir($destinationDir, 0755, true) && ! is_dir($destinationDir)) {
                throw new \RuntimeException("Failed to mkdir {$destinationDir}");
            }

            $contents = file_get_contents($stub['source']);
            if ($contents === false) {
                throw new \RuntimeException("Failed to read stub {$stub['source']}");
            }

            $contents = $substituter->substitute($contents);

            if (file_put_contents($destination, $contents) === false) {
                throw new \RuntimeException("Failed to write {$destination}");
            }

            ++$count;
        }

        return $count;
    }
}
