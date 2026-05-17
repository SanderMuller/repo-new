<?php declare(strict_types=1);

namespace SanderMuller\RepoNew\Scaffolder;

use RuntimeException;
use SanderMuller\RepoNew\Composer\ComposerRunnerInterface;
use SanderMuller\RepoNew\RepoInit\PerCategoryDeps;
use SanderMuller\RepoNew\RepoInit\PlaceholderSubstituter;
use SanderMuller\RepoNew\RepoInit\StubReader;
use SanderMuller\RepoNew\Wizard\WizardState;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Scaffolds a laravel-project: shells out to `laravel new` and overlays the
 * stubs/laravel-project/ additions on top.
 */
final class LaravelProjectScaffolder
{
    public function __construct(
        private readonly SymfonyStyle $io,
        private readonly StubReader $stubReader,
        private readonly PerCategoryDeps $deps,
        private readonly ComposerRunnerInterface $composer,
    ) {}

    /**
     * @return array{stubsWritten: int, requireDevInstalled: int}
     */
    public function scaffold(WizardState $state, string $targetDir): array
    {
        $laravelBin = (new ExecutableFinder())->find('laravel');
        if ($laravelBin === null) {
            throw new RuntimeException(
                'laravel binary not found on PATH. Install via `composer global require laravel/installer` first.',
            );
        }

        // Run `laravel new <name>` from the parent of the target dir. The CLI
        // creates the dir for us, so it must NOT exist yet (or be empty).
        $parent = dirname($targetDir);
        $name = basename($targetDir);

        // If target dir already exists and is empty, laravel new may refuse;
        // use `--force` to overlay or pre-clean. For simplicity, the resolver
        // only mkdir'd the directory; remove it so `laravel new` can create it.
        if (is_dir($targetDir) && $this->isEmpty($targetDir)) {
            @rmdir($targetDir);
        }

        $process = new Process([$laravelBin, 'new', $name, '--boost', '--git', '--no-interaction'], $parent, null, null, 600.0);
        $this->io->writeln("<info>→ laravel new {$name}</info>");
        $process->run(function (string $type, string $buffer): void {
            $this->io->write($buffer);
        });

        if (! $process->isSuccessful()) {
            throw new RuntimeException("laravel new {$name} failed (exit {$process->getExitCode()})");
        }

        // Overlay stubs/laravel-project additions.
        $substituter = new PlaceholderSubstituter($state);
        $written = 0;

        foreach ($this->stubReader->read('laravel-project') as $stub) {
            $relativeSubstituted = $substituter->substitute($stub['relative']);
            $destination = $targetDir . '/' . $relativeSubstituted;

            $destinationDir = dirname($destination);
            if (! is_dir($destinationDir) && ! mkdir($destinationDir, 0755, true) && ! is_dir($destinationDir)) {
                throw new RuntimeException("Failed to mkdir {$destinationDir}");
            }

            $contents = file_get_contents($stub['source']);
            if ($contents === false) {
                throw new RuntimeException("Failed to read {$stub['source']}");
            }

            file_put_contents($destination, $substituter->substitute($contents));
            ++$written;
        }

        $optInFlags = [
            'with-hihaho-rules' => $state->withHihahoRules,
            'with-security-advisories' => $state->withSecurityAdvisories,
        ];
        $depList = $this->deps->forCategory('laravel-project', null, $state->testFramework ?? 'phpunit', $optInFlags);
        $requireDev = array_map(static fn (string $e): string => $substituter->substitute($e), $depList->requireDev);

        if ($requireDev !== []) {
            // Pre-config allow-plugins for plugins our deps will pull in.
            // Laravel ships some (pestphp/pest-plugin, php-http/discovery)
            // but not phpstan/extension-installer which our shared dep list
            // requires. Without this, composer aborts with "contains a
            // Composer plugin which is blocked by your allow-plugins config".
            $this->preAllowPlugins($targetDir, [
                'phpstan/extension-installer',
            ]);

            // For packages that Laravel 13+ ships in `require` but we want in
            // `require-dev` (laravel/tinker is the canonical case), explicitly
            // remove from require first so `composer require --dev` doesn't
            // just leave them in require. Composer's auto-move warning isn't
            // reliable across versions and can leave packages in the wrong
            // scope when the require-dev call has other resolution conflicts.
            $alreadyInRequire = $this->listPackagesInRequire($targetDir);
            $toMove = array_values(array_intersect($requireDev, $alreadyInRequire));

            if ($toMove !== []) {
                $this->io->writeln('<comment>→ moving to require-dev: ' . implode(', ', $toMove) . '</comment>');
                $this->composer->remove($targetDir, $toMove, noUpdate: true);
            }

            // Now install everything as --dev. tinker (or any moved package)
            // re-enters via require-dev cleanly.
            $this->composer->require($targetDir, $requireDev, dev: true);
        }

        return ['stubsWritten' => $written, 'requireDevInstalled' => count($requireDev)];
    }

    /**
     * Add each plugin name to composer.json `config.allow-plugins`.
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
            // Non-fatal if it fails — composer require will surface real errors.
        }
    }

    /**
     * Return the names of packages currently in target's composer.json `require` (non-dev).
     *
     * @return list<string>
     */
    private function listPackagesInRequire(string $targetDir): array
    {
        $composerJson = $targetDir . '/composer.json';
        if (! is_file($composerJson)) {
            return [];
        }

        $raw = file_get_contents($composerJson);
        if ($raw === false) {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded) || ! isset($decoded['require']) || ! is_array($decoded['require'])) {
            return [];
        }

        return array_values(array_keys($decoded['require']));
    }

    private function isEmpty(string $dir): bool
    {
        $entries = @scandir($dir);
        if ($entries === false) {
            return false;
        }

        return array_values(array_diff($entries, ['.', '..'])) === [];
    }
}
