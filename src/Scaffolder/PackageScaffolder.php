<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\Scaffolder;

use RuntimeException;
use SanderMuller\RepoNew\Composer\ComposerJson;
use SanderMuller\RepoNew\Composer\ComposerRunnerInterface;
use SanderMuller\RepoNew\Composer\PluginAllowlist;
use SanderMuller\RepoNew\RepoInit\PerCategoryDeps;
use SanderMuller\RepoNew\RepoInit\PlaceholderSubstituter;
use SanderMuller\RepoNew\RepoInit\StubReader;
use SanderMuller\RepoNew\Wizard\PhpVersionPolicy;
use SanderMuller\RepoNew\Wizard\WizardState;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Scaffolds php-package, laravel-package, phpstan-extension, rector-extension,
 * composer-plugin, skill-bundle.
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
        // Which stubs/shared/ files this category skips is repo-init's
        // `shared-stub-skip` denylist (per-category-deps.yml).
        $sharedSkipper = new SharedStubSkipper($this->deps->sharedStubSkipFor($state->category ?? ''));
        $written += $this->copyStubs('shared', $targetDir, $substituter, $sharedSkipper);

        $category = $state->category ?? '';
        $framework = $state->testFramework ?? 'pest';

        $stubDir = $this->deps->stubDirFor($category, $state->variant);
        $written += $this->copyStubs($stubDir, $targetDir, $substituter);

        if ($category === 'composer-plugin') {
            $this->selectPluginShapeFiles($targetDir, $state->pluginShape ?? 'none');
        }

        // skill-bundle ships no PHP and no test runner, so it gets no test-framework overlay.
        if ($category !== 'skill-bundle') {
            $written += $this->copyStubs("test-framework-{$framework}", $targetDir, $substituter);
            $this->overlayFrameworkNativeCiMatrix($category, $stubDir, $framework, $targetDir, $substituter);
            $this->dropLaravel12CellsOutsideRange($category, $state->laravelVersions, $targetDir);
            new TestFrameworkSwapper($this->deps)->apply($targetDir, $category, $framework);
        }

        new WorkflowPhpVersion()->apply($targetDir, $state->phpVersion ?? PhpVersionPolicy::defaultFor($category));

        $optInFlags = $this->optInFlagsFromState($state);
        $depList = $this->deps->forCategory($category, $framework, $optInFlags);

        // Substitute placeholders in dep constraints (e.g. illuminate/support: __LARAVEL_VERSIONS__).
        $require = $this->withoutAlreadyRequired($targetDir, array_map($substituter->substitute(...), $depList->require));
        $requireDev = $this->withoutAlreadyRequired($targetDir, array_map($substituter->substitute(...), $depList->requireDev));

        // Pre-allow plugins our deps will pull in. Without this, composer
        // aborts with "contains a Composer plugin which is blocked by your
        // allow-plugins config". Set BEFORE install/require so the first
        // composer call already sees them allowed. pestphp/pest-plugin only
        // when actually installing pest (otherwise it lingers in
        // composer.json as a stale allow-plugin entry for nothing).
        // skill-bundle pulls no plugin-bearing deps (boost-core is type:
        // library), so it needs no pre-allow.
        if ($category !== 'skill-bundle') {
            $plugins = ['phpstan/extension-installer'];
            if ($framework === 'pest') {
                $plugins[] = 'pestphp/pest-plugin';
            }

            new PluginAllowlist()->allow($targetDir, $plugins);
        }

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
     * Post-process composer-plugin stubs: keep only the chosen Plugin variant
     * (renamed to canonical src/Plugin.php), drop the rest. Drop CommandProvider
     * sibling unless shape includes command-provider.
     *
     * Stubs ship 4 src/Plugin.{none,command-provider,event-subscriber,both}.php
     * variants + a CommandProvider.php. Generic copyStubs copies them all;
     * this step culls to the user's selected shape.
     */
    private function selectPluginShapeFiles(string $targetDir, string $shape): void
    {
        $srcDir = $targetDir . '/src';
        $variants = ['none', 'command-provider', 'event-subscriber', 'both'];

        foreach ($variants as $variant) {
            $variantFile = $srcDir . '/Plugin.' . $variant . '.php';
            if (! is_file($variantFile)) {
                continue;
            }

            if ($variant === $shape) {
                $canonical = $srcDir . '/Plugin.php';
                if (! rename($variantFile, $canonical)) {
                    throw new RuntimeException("Failed to rename {$variantFile} → {$canonical}");
                }

                continue;
            }

            if (! unlink($variantFile)) {
                throw new RuntimeException("Failed to delete unused plugin variant {$variantFile}");
            }
        }

        if (! in_array($shape, ['command-provider', 'both'], true)) {
            $commandProvider = $srcDir . '/CommandProvider.php';
            if (is_file($commandProvider) && ! unlink($commandProvider)) {
                throw new RuntimeException("Failed to delete unused {$commandProvider}");
            }
        }
    }

    /**
     * Generate .ai/, .claude/, .agents/, .cursor/, AGENTS.md, CLAUDE.md, etc.
     * Composer install/require ran with --no-scripts to keep the scaffold flow
     * predictable; we invoke sync explicitly so the scaffold completes with
     * AI tooling wired up. A failed sync fails the scaffold.
     */
    private function runPackageBoostSync(string $targetDir): void
    {
        $boost = $targetDir . '/vendor/bin/boost';
        if (! is_file($boost)) {
            return;
        }

        $process = new Process([$boost, 'sync'], $targetDir, null, null, 120.0);
        $this->io->writeln('<info>→ boost sync</info>');
        $process->run(function (string $type, string $buffer): void {
            $this->io->write($buffer);
        });

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                'boost sync failed (exit ' . $process->getExitCode() . '). '
                . 'AI tooling dirs (.ai/, .claude/, .agents/, AGENTS.md, CLAUDE.md, …) may be missing or partial. '
                . "Fix the error above, then re-run `vendor/bin/boost sync` in {$targetDir}.",
            );
        }
    }

    /**
     * laravel-package's two stubs differ in CI matrix as well as framework:
     * the Pest-flavoured `laravel-package` tests Laravel 13 only (Pest 5 cannot
     * install next to testbench 10); the PHPUnit-flavoured
     * `laravel-package-spatie` also tests Laravel 12. When the chosen framework
     * is not the variant's own, take run-tests.yml from the other stub.
     */
    private function overlayFrameworkNativeCiMatrix(string $category, string $stubDir, string $framework, string $targetDir, PlaceholderSubstituter $substituter): void
    {
        if ($category !== 'laravel-package') {
            return;
        }

        $nativeStubDir = $framework === 'pest' ? 'laravel-package' : 'laravel-package-spatie';
        if ($nativeStubDir === $stubDir) {
            return;
        }

        $this->copyStubs($nativeStubDir, $targetDir, $substituter, only: ['.github/workflows/run-tests.yml']);
    }

    /**
     * A Laravel 12 CI cell cannot install when the package's range excludes
     * Laravel 12 (repo-init version-defaults.md "Laravel majors in the CI matrix").
     */
    private function dropLaravel12CellsOutsideRange(string $category, ?string $laravelVersions, string $targetDir): void
    {
        if ($category !== 'laravel-package' || preg_match('/(?:^|\|)\s*[\^~]?12(?:\.|\s*(?:\||$))/', $laravelVersions ?? '') === 1) {
            return;
        }

        $path = $targetDir . '/.github/workflows/run-tests.yml';
        $yaml = is_file($path) ? file_get_contents($path) : false;
        if ($yaml === false) {
            return;
        }

        // Each cell goes together with the comment line that labels it.
        $updated = (string) preg_replace("#^(?:[ \t]*\#[^\n]*\n)?[ \t]*- \{[^}\n]*laravel: '12\.\*'[^}\n]*\}\n#m", '', $yaml);
        if ($updated !== $yaml && file_put_contents($path, $updated) === false) {
            throw new RuntimeException("Failed to write {$path}");
        }
    }

    /**
     * Drops bare (unconstrained) entries the stub composer.json already
     * requires — `composer require foo/bar` would otherwise re-guess and
     * overwrite the stub's canonical constraint. Constrained entries stay,
     * so the canonical floors are still enforced.
     *
     * @param  list<string>  $entries
     * @return list<string>
     */
    private function withoutAlreadyRequired(string $targetDir, array $entries): array
    {
        $json = ComposerJson::read($targetDir . '/composer.json');
        $present = ComposerJson::map($json, 'require') + ComposerJson::map($json, 'require-dev');

        return array_values(array_filter(
            $entries,
            static fn (string $entry): bool => str_contains($entry, ':') || ! array_key_exists(trim($entry), $present),
        ));
    }

    /**
     * @return array<string, bool>
     */
    private function optInFlagsFromState(WizardState $state): array
    {
        return match ($state->category) {
            'laravel-package' => [
                // The spatie stub already pins spatie/laravel-package-tools; the
                // opt-in only keeps the dep list matching per-category-deps.yml.
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
     * @param  list<string>|null  $only  when set, copy just these stub-relative paths
     */
    private function copyStubs(string $stubDir, string $targetDir, PlaceholderSubstituter $substituter, ?SharedStubSkipper $skipper = null, ?array $only = null): int
    {
        $count = 0;

        foreach ($this->stubReader->read($stubDir) as $stub) {
            if ($skipper?->shouldSkip($stub['relative']) === true) {
                continue;
            }

            if ($only !== null && ! in_array($stub['relative'], $only, true)) {
                continue;
            }

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
