<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\Scaffolder;

use RuntimeException;
use SanderMuller\RepoNew\Composer\ComposerRunnerInterface;
use SanderMuller\RepoNew\RepoInit\PerCategoryDeps;
use SanderMuller\RepoNew\RepoInit\PlaceholderSubstituter;
use SanderMuller\RepoNew\RepoInit\StubReader;
use SanderMuller\RepoNew\Wizard\WizardState;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

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
final readonly class PackageScaffolder
{
    public function __construct(
        private SymfonyStyle $io,
        private StubReader $stubReader,
        private PerCategoryDeps $deps,
        private ComposerRunnerInterface $composer,
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

        // Overlay test-framework-specific stubs (e.g. tests/Pest.php for pest).
        $framework = $state->testFramework ?? 'pest';
        $written += $this->copyStubs("test-framework-{$framework}", $targetDir, $substituter);

        $optInFlags = $this->optInFlagsFromState($state);
        $depList = $this->deps->forCategory($state->category ?? '', $state->variant, $state->testFramework ?? 'pest', $optInFlags);

        // Substitute placeholders in dep constraints (e.g. illuminate/support: __LARAVEL_VERSIONS__).
        $require = array_map($substituter->substitute(...), $depList->require);
        $requireDev = array_map($substituter->substitute(...), $depList->requireDev);

        // Pre-allow plugins our deps will pull in. Without this, composer
        // aborts with "contains a Composer plugin which is blocked by your
        // allow-plugins config". Set BEFORE install/require so the first
        // composer call already sees them allowed.
        $this->preAllowPlugins($targetDir, [
            'phpstan/extension-installer',
            'pestphp/pest-plugin',
        ]);

        $this->composer->install($targetDir);

        if ($require !== []) {
            $this->composer->require($targetDir, $require, dev: false);
        }

        if ($requireDev !== []) {
            $this->composer->require($targetDir, $requireDev, dev: true);
        }

        $this->runPackageBoostSync($targetDir);

        return [
            'stubsWritten' => $written,
            'requireInstalled' => count($require),
            'requireDevInstalled' => count($requireDev),
        ];
    }

    /**
     * Generate .ai/, .claude/, .agents/, .cursor/, AGENTS.md, CLAUDE.md, etc.
     * Composer install/require ran with --no-scripts to keep the scaffold flow
     * predictable; we invoke sync explicitly so the scaffold completes with
     * AI tooling wired up.
     */
    private function runPackageBoostSync(string $targetDir): void
    {
        $testbench = $targetDir . '/vendor/bin/testbench';
        if (! is_file($testbench)) {
            return;
        }

        $process = new Process([$testbench, 'package-boost:sync'], $targetDir, null, null, 120.0);
        $this->io->writeln('<info>→ package-boost:sync</info>');
        $process->run(function (string $type, string $buffer): void {
            $this->io->write($buffer);
        });
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

    /**
     * Rename leading `_` to `.` in each path segment. Stubs in repo-init
     * use `_gitattributes` etc. because real `.gitattributes` files in the
     * source tree get honored by `git archive`/Packagist and strip
     * legitimate stub content from the published source tarball.
     */
    private function dotPrefixRename(string $relative): string
    {
        $segments = array_map(
            static fn (string $s): string => str_starts_with($s, '_') ? '.' . substr($s, 1) : $s,
            explode('/', $relative),
        );

        return implode('/', $segments);
    }

    /**
     * @param  list<string>  $plugins
     */
    private function preAllowPlugins(string $targetDir, array $plugins): void
    {
        foreach ($plugins as $plugin) {
            $process = new Process(
                ['composer', 'config', '--no-plugins', "allow-plugins.{$plugin}", 'true'],
                $targetDir,
                null,
                null,
                60.0,
            );
            $process->run();
        }
    }

    private function copyStubs(string $stubDir, string $targetDir, PlaceholderSubstituter $substituter): int
    {
        $count = 0;

        foreach ($this->stubReader->read($stubDir) as $stub) {
            $relativeSubstituted = $substituter->substitute($stub['relative']);
            $relativeSubstituted = $this->dotPrefixRename($relativeSubstituted);
            $destination = $targetDir . '/' . $relativeSubstituted;

            $destinationDir = dirname($destination);
            if (! is_dir($destinationDir) && ! mkdir($destinationDir, 0755, true) && ! is_dir($destinationDir)) {
                throw new RuntimeException("Failed to mkdir {$destinationDir}");
            }

            $contents = file_get_contents($stub['source']);
            if ($contents === false) {
                throw new RuntimeException("Failed to read stub {$stub['source']}");
            }

            $contents = $substituter->substitute($contents);

            if (file_put_contents($destination, $contents) === false) {
                throw new RuntimeException("Failed to write {$destination}");
            }

            ++$count;
        }

        return $count;
    }
}
